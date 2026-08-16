<?php

namespace Modules\Analytics\Services\Forecasting;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Service de prévision de production.
 *
 * Intégré avec :
 *   - Modules/Manufacturing : ordres de fabrication, nomenclatures (BOM)
 *   - Modules/Inventory : niveaux de stock matières premières
 *   - Modules/Achats : délais fournisseurs
 */
class ProductionForecastService
{
    public function __construct(private readonly ForecastingEngineService $engine) {}

    /**
     * Prévision des ordres de fabrication nécessaires pour répondre à la demande.
     *
     * @return array{predictions: array, summary: array, bottlenecks: array}
     */
    public function forecastProductionNeeds(int $tenantId, int $days = 90): array
    {
        $demandForecast = $this->getDemandForecast($tenantId, $days);
        $capacity       = $this->getDailyCapacity($tenantId);
        $bottlenecks    = $this->detectBottlenecks($tenantId);

        $predictions = [];
        foreach ($demandForecast as $point) {
            $requiredOrders = ceil((float) $point['value'] / max($this->getAvgBatchSize($tenantId), 1));
            $utilization    = $capacity > 0 ? ($requiredOrders / $capacity) * 100 : 0;

            $predictions[] = [
                'date'             => $point['date'],
                'demand'           => $point['value'],
                'production_orders' => (int) $requiredOrders,
                'capacity_units'   => $capacity,
                'utilization_pct'  => round($utilization, 1),
                'status'           => $utilization > 100 ? 'surchargé' : ($utilization > 80 ? 'tendu' : 'normal'),
            ];
        }

        $totalOrders   = array_sum(array_column($predictions, 'production_orders'));
        $avgUtil       = count($predictions) > 0
            ? array_sum(array_column($predictions, 'utilization_pct')) / count($predictions)
            : 0;

        return [
            'tenant_id'    => $tenantId,
            'horizon_days' => $days,
            'predictions'  => $predictions,
            'summary'      => [
                'total_orders'      => $totalOrders,
                'avg_utilization'   => round($avgUtil, 1),
                'overloaded_days'   => count(array_filter($predictions, fn ($p) => $p['utilization_pct'] > 100)),
                'daily_capacity'    => $capacity,
            ],
            'bottlenecks'  => $bottlenecks,
        ];
    }

    /**
     * Prévision de l'utilisation de la capacité par semaine.
     *
     * @return array<int, array{week: string, orders: int, capacity_pct: float, bottleneck_resource: string|null}>
     */
    public function forecastCapacityUtilization(int $tenantId): array
    {
        $needs        = $this->forecastProductionNeeds($tenantId, 90);
        $weeklyGroups = [];

        foreach ($needs['predictions'] as $day) {
            $week = \Carbon\Carbon::parse($day['date'])->startOfWeek()->toDateString();
            if (! isset($weeklyGroups[$week])) {
                $weeklyGroups[$week] = ['orders' => 0, 'utilization_pcts' => []];
            }
            $weeklyGroups[$week]['orders']              += $day['production_orders'];
            $weeklyGroups[$week]['utilization_pcts'][]   = $day['utilization_pct'];
        }

        $result = [];
        foreach ($weeklyGroups as $week => $data) {
            $avgUtil     = array_sum($data['utilization_pcts']) / max(count($data['utilization_pcts']), 1);
            $bottleneck  = $avgUtil > 90 ? $this->identifyBottleneckResource($tenantId) : null;

            $result[] = [
                'week'               => $week,
                'orders'             => $data['orders'],
                'capacity_pct'       => round($avgUtil, 1),
                'bottleneck_resource' => $bottleneck,
            ];
        }

        return $result;
    }

    /**
     * Prévision de consommation des matières premières.
     *
     * @return array<int, array{product_id: int, name: string, forecast_consumption: float, current_stock: float, reorder_needed: bool}>
     */
    public function forecastMaterialConsumption(int $tenantId, array $productIds): array
    {
        if (empty($productIds)) {
            $productIds = DB::table('bom_components')
                ->where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('component_id')
                ->toArray();
        }

        $result = [];
        foreach ($productIds as $productId) {
            $dailyUsage = $this->getDailyMaterialUsage($productId, $tenantId);
            $forecast90 = $dailyUsage * 90;
            $stock      = $this->getCurrentStock($productId, $tenantId);

            $result[] = [
                'product_id'           => $productId,
                'name'                 => $this->getProductName($productId),
                'daily_usage'          => round($dailyUsage, 2),
                'forecast_consumption' => round($forecast90, 2),
                'current_stock'        => round($stock, 2),
                'coverage_days'        => $dailyUsage > 0 ? round($stock / $dailyUsage) : 999,
                'reorder_needed'       => $stock < $dailyUsage * 30, // moins de 30 jours de stock
                'reorder_qty'          => max(0, round($dailyUsage * 60 - $stock, 0)), // pour 60 jours
            ];
        }

        return $result;
    }

    /**
     * Détecte les goulots d'étranglement à partir des prévisions.
     *
     * @return array<int, array{resource: string, type: string, severity: string, message: string, predicted_date: string}>
     */
    public function detectBottlenecks(int $tenantId): array
    {
        $bottlenecks = [];

        // Vérifier la capacité machine
        $machines = DB::table('work_centers')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'capacity_per_day')
            ->get();

        $avgOrders = $this->getAverageDailyOrders($tenantId);

        foreach ($machines as $machine) {
            $util = (float) $machine->capacity_per_day > 0
                ? $avgOrders / (float) $machine->capacity_per_day * 100
                : 0;

            if ($util > 90) {
                $bottlenecks[] = [
                    'resource'      => $machine->name,
                    'type'          => 'machine',
                    'severity'      => $util > 100 ? 'critical' : 'warning',
                    'message'       => "Taux d'utilisation prévu : {$util}% — risque de retard de production",
                    'predicted_date' => now()->addDays(7)->toDateString(),
                    'utilization'   => round($util, 1),
                ];
            }
        }

        // Vérifier les matières premières critiques
        $criticalMaterials = $this->forecastMaterialConsumption($tenantId, []);
        foreach (array_filter($criticalMaterials, fn ($m) => $m['reorder_needed']) as $material) {
            $bottlenecks[] = [
                'resource'      => $material['name'],
                'type'          => 'material',
                'severity'      => $material['coverage_days'] < 7 ? 'critical' : 'warning',
                'message'       => "Stock couvrant seulement {$material['coverage_days']} jours — réapprovisionner",
                'predicted_date' => now()->addDays($material['coverage_days'])->toDateString(),
                'coverage_days' => $material['coverage_days'],
            ];
        }

        return $bottlenecks;
    }

    // ─── Méthodes privées ─────────────────────────────────────────

    private function getDemandForecast(int $tenantId, int $days): array
    {
        $history = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->selectRaw('DATE(so.created_at) as date, SUM(sol.quantity) as value')
            ->where('so.tenant_id', $tenantId)
            ->where('so.created_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])
            ->toArray();

        return empty($history) ? [] : $this->engine->linearRegression($history, $days);
    }

    private function getDailyCapacity(int $tenantId): int
    {
        return (int) DB::table('work_centers')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->sum('capacity_per_day') ?: 50;
    }

    private function getAvgBatchSize(int $tenantId): float
    {
        return (float) DB::table('manufacturing_orders')
            ->where('tenant_id', $tenantId)
            ->avg('quantity') ?? 10.0;
    }

    private function getAverageDailyOrders(int $tenantId): float
    {
        return (float) DB::table('manufacturing_orders')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonths(3))
            ->selectRaw('COUNT(*) / DATEDIFF(NOW(), MIN(created_at)) as avg')
            ->value('avg') ?? 0.0;
    }

    private function identifyBottleneckResource(int $tenantId): ?string
    {
        return DB::table('work_centers')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('capacity_per_day')
            ->value('name');
    }

    private function getDailyMaterialUsage(int $productId, int $tenantId): float
    {
        return (float) DB::table('stock_movements')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('type', 'consumption')
            ->where('created_at', '>=', now()->subMonths(3))
            ->selectRaw('SUM(quantity) / DATEDIFF(NOW(), MIN(created_at)) as daily_avg')
            ->value('daily_avg') ?? 0.0;
    }

    private function getCurrentStock(int $productId, int $tenantId): float
    {
        return (float) DB::table('stock_levels')
            ->where('product_id', $productId)
            ->where('tenant_id', $tenantId)
            ->value('quantity') ?? 0.0;
    }

    private function getProductName(int $productId): string
    {
        return DB::table('products')->where('id', $productId)->value('name') ?? "Produit #{$productId}";
    }
}
