<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\AI;

use Illuminate\Support\Collection;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\Product;

class ABCAnalysisService
{
    /**
     * Perform ABC analysis on inventory (Pareto principle)
     * A: ~20% of items = ~80% of value (high priority)
     * B: ~30% of items = ~15% of value (medium priority)
     * C: ~50% of items = ~5% of value (low priority)
     */
    public function analyzeInventory(string $metric = 'value', ?int $warehouseId = null): array
    {
        $products = $this->getProductsForAnalysis($warehouseId);

        if ($products->isEmpty()) {
            return ['error' => 'No inventory data available'];
        }

        // Calculate metric for each product
        $productsWithMetrics = $this->calculateMetrics($products, $metric);

        // Sort by metric descending
        $sorted = $productsWithMetrics->sortByDesc('metric')->values();

        // Calculate cumulative percentages
        $totalMetric = $sorted->sum('metric');
        $cumulative = 0;

        $classified = $sorted->map(function ($item) use ($totalMetric, &$cumulative) {
            $cumulative += $item['metric'];
            $percentage = ($cumulative / $totalMetric) * 100;

            // Classify based on cumulative percentage
            $classification = match (true) {
                $percentage <= 80 => 'A',
                $percentage <= 95 => 'B',
                default => 'C',
            };

            return array_merge($item, [
                'cumulative_percentage' => round($percentage, 2),
                'classification' => $classification,
            ]);
        });

        // Group by classification
        $grouped = $classified->groupBy('classification');

        return [
            'total_products' => $products->count(),
            'total_metric_value' => $totalMetric,
            'metric_type' => $metric,
            'classifications' => [
                'A' => $this->buildClassificationStats('A', $grouped->get('A', collect()), $totalMetric),
                'B' => $this->buildClassificationStats('B', $grouped->get('B', collect()), $totalMetric),
                'C' => $this->buildClassificationStats('C', $grouped->get('C', collect()), $totalMetric),
            ],
            'products' => $classified->toArray(),
            'recommendations' => $this->generateRecommendations($classified),
        ];
    }

    /**
     * Get inventory movement velocity analysis
     */
    public function analyzeVelocity(?int $warehouseId = null, int $daysBack = 90): array
    {
        $movements = InventoryMovement::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->get();

        $productVelocities = [];

        foreach ($movements->groupBy('product_id') as $productId => $items) {
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $velocity = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'movement_count' => $items->count(),
                'avg_daily_movement' => round($items->count() / $daysBack, 2),
                'classification' => $this->classifyVelocity($items->count() / $daysBack),
            ];

            $productVelocities[] = $velocity;
        }

        // Sort by movement count
        usort($productVelocities, fn ($a, $b) => $b['movement_count'] <=> $a['movement_count']);

        return [
            'period_days' => $daysBack,
            'total_movements' => $movements->count(),
            'products_analyzed' => count($productVelocities),
            'velocities' => $productVelocities,
            'velocity_summary' => [
                'fast_moving' => count(array_filter($productVelocities, fn ($p) => $p['classification'] === 'fast')),
                'normal_moving' => count(array_filter($productVelocities, fn ($p) => $p['classification'] === 'normal')),
                'slow_moving' => count(array_filter($productVelocities, fn ($p) => $p['classification'] === 'slow')),
                'dead_stock' => count(array_filter($productVelocities, fn ($p) => $p['classification'] === 'dead')),
            ],
        ];
    }

    /**
     * Generate strategic recommendations based on ABC analysis
     */
    public function generateRecommendations(Collection $classified): array
    {
        $itemsByClass = $classified->groupBy('classification');

        $recommendations = [];

        // A-Class recommendations
        if ($itemsByClass->has('A')) {
            $aCount = $itemsByClass['A']->count();
            $recommendations[] = [
                'class' => 'A',
                'priority' => 'critical',
                'recommendations' => [
                    'Implement strict inventory control and monitoring',
                    'Use ABC forecasting for demand planning',
                    'Maintain higher safety stock levels',
                    'Schedule regular physical counts (monthly)',
                    'Focus on supplier relationship management',
                    "Review $aCount items at least monthly",
                ],
            ];
        }

        // B-Class recommendations
        if ($itemsByClass->has('B')) {
            $bCount = $itemsByClass['B']->count();
            $recommendations[] = [
                'class' => 'B',
                'priority' => 'medium',
                'recommendations' => [
                    'Implement periodic review system',
                    'Use standard ABC forecasting',
                    "Schedule physical counts quarterly for $bCount items",
                    'Maintain standard safety stock',
                    'Monitor for potential reclassification to A or C',
                ],
            ];
        }

        // C-Class recommendations
        if ($itemsByClass->has('C')) {
            $cCount = $itemsByClass['C']->count();
            $recommendations[] = [
                'class' => 'C',
                'priority' => 'low',
                'recommendations' => [
                    'Minimize control efforts and costs',
                    'Use simplified forecasting methods',
                    'Review for potential obsolescence',
                    "Schedule physical counts semi-annually for $cCount items",
                    'Consider bulk ordering to reduce order frequency',
                    'Evaluate demand-driven vs. stock-driven replenishment',
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate optimal order quantities based on ABC classification
     */
    public function calculateOptimalOrderQuantities(
        int $productId,
        float $holdingCost,
        float $orderingCost,
        ?int $warehouseId = null
    ): array {
        $product = Product::findOrFail($productId);

        // Get annual demand
        $demand = $this->getAnnualDemand($productId, $warehouseId);

        // Get ABC classification
        $analysis = $this->analyzeInventory('value', $warehouseId);
        $productAnalysis = collect($analysis['products'])->firstWhere('id', $productId);
        $classification = $productAnalysis['classification'] ?? 'C';

        // Adjust parameters based on classification
        $adjustedHoldingCost = $holdingCost * match ($classification) {
            'A' => 1.0, // Full holding cost
            'B' => 1.2, // Slightly higher due to medium importance
            'C' => 0.8, // Slightly lower due to low importance
        };

        // Economic Order Quantity (EOQ) = sqrt(2DS/H)
        $eoq = sqrt((2 * $demand * $orderingCost) / $adjustedHoldingCost);

        // Reorder Point (ROP) = d * L + SS
        // Where d = daily demand, L = lead time, SS = safety stock
        $dailyDemand = $demand / 365;
        $leadTime = $this->getAverageLeadTime($productId) ?? 7;
        $safetyStock = $this->calculateSafetyStock($classification, $dailyDemand, $leadTime);
        $rop = ($dailyDemand * $leadTime) + $safetyStock;

        return [
            'product_id' => $productId,
            'product_name' => $product->name,
            'classification' => $classification,
            'annual_demand' => round($demand, 2),
            'daily_demand' => round($dailyDemand, 2),
            'economic_order_quantity' => round($eoq, 2),
            'reorder_point' => round($rop, 2),
            'safety_stock' => round($safetyStock, 2),
            'optimal_order_frequency' => round($demand / $eoq, 1),
            'expected_ordering_cost' => round(($demand / $eoq) * $orderingCost, 2),
            'expected_holding_cost' => round(($eoq / 2) * $adjustedHoldingCost, 2),
            'total_annual_cost' => round((($demand / $eoq) * $orderingCost) + (($eoq / 2) * $adjustedHoldingCost), 2),
        ];
    }

    private function getProductsForAnalysis(?int $warehouseId): Collection
    {
        return Product::query()
            ->when($warehouseId, fn ($q) => $q->whereHas('stock', fn ($sq) => $sq->where('warehouse_id', $warehouseId)))
            ->with(['stock', 'movements'])
            ->get();
    }

    private function calculateMetrics(Collection $products, string $metric): Collection
    {
        return $products->map(function ($product) use ($metric) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'metric' => match ($metric) {
                    'value' => $this->calculateInventoryValue($product),
                    'quantity' => $this->calculateTotalQuantity($product),
                    'turnover' => $this->calculateTurnoverRate($product),
                    'demand' => $this->getAnnualDemand($product->id),
                    default => $this->calculateInventoryValue($product),
                },
            ];
        });
    }

    private function calculateInventoryValue($product): float
    {
        return $product->stock->sum(fn ($s) => $s->quantity * ($product->cost_price ?? 0));
    }

    private function calculateTotalQuantity($product): float
    {
        return $product->stock->sum('quantity');
    }

    private function calculateTurnoverRate($product): float
    {
        $costOfGoodsSold = $product->movements
            ->where('type', 'out')
            ->sum(fn ($m) => $m->quantity * ($product->cost_price ?? 0));

        $averageInventoryValue = $this->calculateInventoryValue($product) / 2;

        return $averageInventoryValue > 0 ? $costOfGoodsSold / $averageInventoryValue : 0;
    }

    private function getAnnualDemand(int $productId, ?int $warehouseId = null): float
    {
        $movements = InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('type', 'out')
            ->where('created_at', '>=', now()->subYear())
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');

        return $movements;
    }

    private function getAverageLeadTime(int $productId): ?int
    {
        // Could be enhanced with supplier data
        return 7; // Default 7 days
    }

    private function calculateSafetyStock(string $classification, float $dailyDemand, int $leadTime): float
    {
        // Safety stock formula: Z * σ * sqrt(L)
        // For simplicity, use classification-based multipliers
        $zScore = match ($classification) {
            'A' => 2.33, // 99% service level
            'B' => 1.65, // 95% service level
            'C' => 0.84, // 80% service level
            default => 1.65,
        };

        // Standard deviation estimate (10-20% of daily demand)
        $sigma = $dailyDemand * 0.15;

        return $zScore * $sigma * sqrt($leadTime);
    }

    private function buildClassificationStats(string $class, Collection $items, float $totalMetric): array
    {
        if ($items->isEmpty()) {
            return [
                'class' => $class,
                'item_count' => 0,
                'item_percentage' => 0,
                'value_percentage' => 0,
            ];
        }

        $itemValue = $items->sum('metric');

        return [
            'class' => $class,
            'item_count' => $items->count(),
            'item_percentage' => round(($items->count() / count(collect($items)->flatten())) * 100, 2),
            'value_percentage' => round(($itemValue / $totalMetric) * 100, 2),
            'total_value' => round($itemValue, 2),
        ];
    }

    private function classifyVelocity(float $dailyMovement): string
    {
        return match (true) {
            $dailyMovement >= 1.0 => 'fast',
            $dailyMovement >= 0.5 => 'normal',
            $dailyMovement >= 0.1 => 'slow',
            default => 'dead',
        };
    }
}
