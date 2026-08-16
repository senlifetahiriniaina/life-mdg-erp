<?php

namespace Modules\Analytics\Services\Forecasting;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Service de prévision de la demande.
 *
 * Africa First :
 *   - Saisonnalité Ramadan (variable chaque année selon le calendrier hégirien)
 *   - Rentrée scolaire (septembre / octobre en Afrique subsaharienne)
 *   - Saison des récoltes (octobre–décembre pour les zones sahéliennes)
 *   - Saisonnalité Tabaski / Aïd al-Adha
 */
class DemandForecastService
{
    public function __construct(private readonly ForecastingEngineService $engine) {}

    /**
     * Prévision de la demande d'un produit pour les N prochains jours.
     *
     * @return array{predictions: array, seasonality: array, reorder_suggestion: float, narrative: string}
     */
    public function forecastProduct(int $productId, int $tenantId, int $days = 90): array
    {
        $data = $this->engine->collectHistoricalData('demand', 'product', $productId, $tenantId);

        if (empty($data)) {
            return $this->emptyForecast($days);
        }

        $predictions = $this->engine->linearRegression($data, $days);
        $seasonality = $this->detectSeasonality($data);

        // Appliquer les coefficients saisonniers aux prévisions
        $predictions = $this->applySeasonality($predictions, $seasonality);

        $totalForecast = array_sum(array_column($predictions, 'value'));
        $dailyAvg      = $totalForecast / max($days, 1);

        return [
            'product_id'          => $productId,
            'horizon_days'        => $days,
            'predictions'         => $predictions,
            'seasonality'         => $seasonality,
            'total_forecast'      => round($totalForecast, 2),
            'daily_avg'           => round($dailyAvg, 2),
            'reorder_suggestion'  => round($dailyAvg * 14, 2), // 2 semaines de sécurité
            'narrative'           => $this->buildNarrative($predictions, $seasonality),
        ];
    }

    /**
     * Prévision de la demande par catégorie de produit.
     *
     * @return array{category: string, predictions: array, top_products: array}
     */
    public function forecastCategory(string $category, int $tenantId, int $days = 90): array
    {
        // Agréger les ventes par catégorie
        $rows = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->join('inventory_products as p', 'p.id', '=', 'sol.product_id')
            ->selectRaw('DATE(so.created_at) as date, SUM(sol.quantity) as value')
            ->where('so.tenant_id', $tenantId)
            ->where('p.category', $category)
            ->where('so.created_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])
            ->toArray();

        $predictions = empty($rows) ? [] : $this->engine->linearRegression($rows, $days);

        // Top 5 produits de la catégorie
        $topProducts = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
            ->join('inventory_products as p', 'p.id', '=', 'sol.product_id')
            ->selectRaw('sol.product_id, p.name, SUM(sol.quantity) as total_qty')
            ->where('so.tenant_id', $tenantId)
            ->where('p.category', $category)
            ->where('so.created_at', '>=', now()->subMonths(3))
            ->groupBy('sol.product_id', 'p.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'category'    => $category,
            'horizon_days' => $days,
            'predictions' => $predictions,
            'top_products' => $topProducts,
        ];
    }

    /**
     * Suggère des points de réappro optimisés basés sur les prévisions.
     *
     * @return array<int, array{product_id: int, current_reorder_point: float, suggested_reorder_point: float, reason: string}>
     */
    public function suggestReorderPoints(int $tenantId): array
    {
        $products = DB::table('inventory_products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'reorder_point')
            ->limit(100)
            ->get();

        $suggestions = [];
        foreach ($products as $product) {
            $forecast  = $this->forecastProduct($product->id, $tenantId, 30);
            $dailyAvg  = $forecast['daily_avg'];
            $leadTime  = $product->lead_time_days ?? 7;
            $safetyDays = 3; // jours de stock de sécurité

            $suggested = $dailyAvg * ($leadTime + $safetyDays);
            $current   = (float) ($product->reorder_point ?? 0);

            if (abs($suggested - $current) > $current * 0.1) { // écart > 10 %
                $suggestions[] = [
                    'product_id'             => $product->id,
                    'product_name'           => $product->name,
                    'current_reorder_point'  => $current,
                    'suggested_reorder_point' => round($suggested, 0),
                    'reason'                 => $suggested > $current
                        ? "Demande en hausse : {$dailyAvg}/j × (délai {$leadTime}j + sécurité {$safetyDays}j)"
                        : "Demande en baisse : optimisation du stock immobilisé",
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Détecte les patterns saisonniers (hebdomadaire, mensuel, annuel).
     * Africa First : Ramadan, Tabaski, rentrée scolaire, saisons agricoles.
     *
     * @return array{weekly: float[], monthly: float[], african_events: array}
     */
    public function detectSeasonality(array $data): array
    {
        $decomposed = $this->engine->decompose($data);

        // Saisonnalité hebdomadaire (indices par jour de la semaine 0=lundi)
        $weeklyIndices = array_fill(0, 7, []);
        foreach ($data as $i => $point) {
            $dow = \Carbon\Carbon::parse($point['date'])->dayOfWeek;
            $weeklyIndices[$dow][] = $decomposed['seasonal'][$i] ?? 1.0;
        }
        $weekly = array_map(
            fn ($vals) => count($vals) > 0 ? array_sum($vals) / count($vals) : 1.0,
            $weeklyIndices
        );

        // Saisonnalité mensuelle (indices par mois 1–12)
        $monthlyIndices = array_fill(1, 12, []);
        foreach ($data as $i => $point) {
            $month = (int) \Carbon\Carbon::parse($point['date'])->format('n');
            $monthlyIndices[$month][] = $decomposed['seasonal'][$i] ?? 1.0;
        }
        $monthly = array_map(
            fn ($vals) => count($vals) > 0 ? array_sum($vals) / count($vals) : 1.0,
            $monthlyIndices
        );

        // Événements Africa First pour l'année courante
        $africanEvents = $this->getAfricanSeasonalEvents();

        return [
            'weekly'         => array_values($weekly),
            'monthly'        => array_values($monthly),
            'african_events' => $africanEvents,
        ];
    }

    // ─── Méthodes privées ─────────────────────────────────────────

    private function applySeasonality(array $predictions, array $seasonality): array
    {
        return array_map(function ($p) use ($seasonality) {
            $dow   = \Carbon\Carbon::parse($p['date'])->dayOfWeek;
            $coeff = $seasonality['weekly'][$dow] ?? 1.0;

            return array_merge($p, [
                'value' => round($p['value'] * $coeff, 4),
                'lower' => round($p['lower'] * $coeff, 4),
                'upper' => round($p['upper'] * $coeff, 4),
            ]);
        }, $predictions);
    }

    private function buildNarrative(array $predictions, array $seasonality): string
    {
        $values = array_column($predictions, 'value');
        if (empty($values)) {
            return 'Données insuffisantes pour une analyse narrative.';
        }

        $total = array_sum($values);
        $peak  = max($values);
        $events = $seasonality['african_events'] ?? [];
        $upcoming = array_filter($events, fn ($e) => strtotime($e['start']) >= time());

        $narrative = "Demande totale prévue : " . number_format($total, 0, ',', ' ') . " unités. ";
        $narrative .= "Pic journalier : " . number_format($peak, 0, ',', ' ') . " unités. ";

        if (! empty($upcoming)) {
            $event = array_shift($upcoming);
            $narrative .= "Événement saisonnier à venir : {$event['name']} ({$event['start']}) — prévoir un stock supplémentaire.";
        }

        return $narrative;
    }

    private function getAfricanSeasonalEvents(): array
    {
        $year = (int) date('Y');

        // Événements clés pour les marchés africains (dates approximatives)
        return [
            [
                'name'        => 'Ramadan',
                'start'       => date('Y-m-d', strtotime("{$year}-03-01")), // approximatif — varie chaque année
                'end'         => date('Y-m-d', strtotime("{$year}-03-30")),
                'impact'      => 'hausse',
                'sectors'     => ['alimentation', 'textile', 'électronique'],
                'countries'   => ['SN', 'CI', 'ML', 'BF', 'NE', 'MR', 'MA', 'DZ', 'TN', 'EG'],
            ],
            [
                'name'        => 'Tabaski / Aïd al-Adha',
                'start'       => date('Y-m-d', strtotime("{$year}-06-05")), // approximatif
                'end'         => date('Y-m-d', strtotime("{$year}-06-07")),
                'impact'      => 'hausse',
                'sectors'     => ['alimentation', 'textile', 'bétail'],
                'countries'   => ['SN', 'CI', 'ML', 'GN', 'NE', 'MR'],
            ],
            [
                'name'        => 'Rentrée scolaire',
                'start'       => "{$year}-09-01",
                'end'         => "{$year}-10-15",
                'impact'      => 'hausse',
                'sectors'     => ['fournitures', 'textile', 'livres'],
                'countries'   => ['SN', 'CI', 'CM', 'GH', 'MG', 'BJ'],
            ],
            [
                'name'        => 'Saison des récoltes (Sahel)',
                'start'       => "{$year}-10-01",
                'end'         => "{$year}-12-15",
                'impact'      => 'hausse',
                'sectors'     => ['agriculture', 'transport', 'stockage'],
                'countries'   => ['SN', 'ML', 'BF', 'NE', 'NG'],
            ],
            [
                'name'        => 'Fêtes de fin d\'année',
                'start'       => "{$year}-12-15",
                'end'         => "{$year}-12-31",
                'impact'      => 'hausse',
                'sectors'     => ['électronique', 'alimentation', 'cadeaux'],
                'countries'   => ['SN', 'CI', 'CM', 'GH', 'MG', 'KE'],
            ],
        ];
    }

    private function emptyForecast(int $days): array
    {
        return [
            'product_id'          => null,
            'horizon_days'        => $days,
            'predictions'         => [],
            'seasonality'         => ['weekly' => [], 'monthly' => [], 'african_events' => $this->getAfricanSeasonalEvents()],
            'total_forecast'      => 0.0,
            'daily_avg'           => 0.0,
            'reorder_suggestion'  => 0.0,
            'narrative'           => 'Aucune donnée historique disponible pour ce produit.',
        ];
    }
}
