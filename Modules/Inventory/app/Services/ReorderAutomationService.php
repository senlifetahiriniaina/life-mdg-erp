<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Product;
use Modules\Achats\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Inventory Reorder Automation Service
 * Auto-generates purchase orders when stock falls below reorder point.
 * Includes supplier preference assignment and seasonal adjustment.
 */
class ReorderAutomationService
{
    /**
     * Check all products and generate reorders if needed
     */
    public function generateReordersForTenant(int $tenantId): array
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->where('status', 'active')
            ->get();

        $reorders = [];
        $skipped = [];

        foreach ($products as $product) {
            if ($this->shouldReorder($product)) {
                try {
                    $order = $this->createPurchaseOrder($product);
                    if ($order) {
                        $reorders[] = $order->id;
                        Log::info("Auto-reorder created for {$product->sku}", ['order_id' => $order->id]);
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to auto-reorder {$product->sku}", ['error' => $e->getMessage()]);
                    $skipped[] = $product->sku;
                }
            }
        }

        return [
            'created_count' => count($reorders),
            'skipped_count' => count($skipped),
            'order_ids' => $reorders,
            'skipped_skus' => $skipped,
        ];
    }

    /**
     * Determine if product should be reordered
     */
    public function shouldReorder(Product $product): bool
    {
        $currentStock = $product->quantity_on_hand;
        $reorderPoint = $this->computeReorderPoint($product);

        if ($currentStock > $reorderPoint) {
            return false;
        }

        // Check if recent order already exists (avoid duplicate orders)
        $recentOrder = PurchaseOrder::where('product_id', $product->id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        return !$recentOrder;
    }

    /**
     * Calculate reorder point with seasonal adjustment
     */
    public function computeReorderPoint(Product $product): float
    {
        $baseReorderPoint = $product->reorder_point ?? 50;
        $seasonalFactor = $this->getSeasonalAdjustmentFactor($product);
        $safetyStock = $product->safety_stock ?? 20;

        return ($baseReorderPoint * $seasonalFactor) + $safetyStock;
    }

    /**
     * Get seasonal adjustment factor (1.0 = no adjustment)
     */
    public function getSeasonalAdjustmentFactor(Product $product): float
    {
        $currentMonth = now()->month;

        // Regional seasonal patterns
        $tenantCountry = $product->tenant?->country ?? 'SN';

        return match ($tenantCountry) {
            'SN', 'CI', 'CM' => $this->getWestAfricanSeasonalFactor($currentMonth),
            'KE', 'TZ', 'UG' => $this->getEastAfricanSeasonalFactor($currentMonth),
            'MG' => $this->getMadagascarSeasonalFactor($currentMonth),
            'NG', 'GH' => $this->getWestAfricanSeasonalFactor($currentMonth),
            default => 1.0,
        };
    }

    /**
     * West African seasonal pattern (Ramadan, school year, harvest)
     */
    private function getWestAfricanSeasonalFactor(int $month): float
    {
        return match ($month) {
            // Ramadan (approx. March-April 2026)
            3, 4 => 1.3,
            // School year starts (September)
            9 => 1.25,
            // Harvest season (October-November)
            10, 11 => 1.15,
            // Low season (May-June)
            5, 6 => 0.8,
            default => 1.0,
        };
    }

    /**
     * East African seasonal pattern
     */
    private function getEastAfricanSeasonalFactor(int $month): float
    {
        return match ($month) {
            // Wet season high demand (March-May, November)
            3, 4, 5, 11 => 1.2,
            // Dry season (January-February)
            1, 2 => 0.9,
            default => 1.0,
        };
    }

    /**
     * Madagascar seasonal pattern
     */
    private function getMadagascarSeasonalFactor(int $month): float
    {
        return match ($month) {
            // Rainy season (November-March)
            11, 12, 1, 2, 3 => 1.15,
            // Dry season (May-October)
            5, 6, 7, 8, 9, 10 => 0.85,
            default => 1.0,
        };
    }

    /**
     * Create purchase order for product
     */
    public function createPurchaseOrder(Product $product): ?PurchaseOrder
    {
        // Get preferred supplier
        $supplier = $this->getPreferredSupplier($product);
        if (!$supplier) {
            Log::warning("No supplier found for {$product->sku}");
            return null;
        }

        // Calculate order quantity
        $quantity = $this->computeOrderQuantity($product);

        $order = PurchaseOrder::create([
            'tenant_id' => $product->tenant_id,
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $supplier->pivot->unit_price ?? $product->cost,
            'total_amount' => $quantity * ($supplier->pivot->unit_price ?? $product->cost),
            'status' => 'pending',
            'order_date' => now(),
            'expected_delivery_date' => $this->computeExpectedDeliveryDate($supplier),
            'notes' => 'Auto-generated reorder (stock fell below reorder point)',
            'auto_generated' => true,
        ]);

        return $order;
    }

    /**
     * Get preferred supplier for product
     */
    public function getPreferredSupplier($product)
    {
        // Priority: preferred supplier > best QQCD score > lowest cost
        $supplier = $product->suppliers()
            ->where('is_preferred', true)
            ->first();

        if ($supplier) {
            return $supplier;
        }

        // Fall back to best QQCD score
        $supplier = $product->suppliers()
            ->orderBy('qqcd_score', 'desc')
            ->first();

        if ($supplier) {
            return $supplier;
        }

        // Fall back to lowest cost
        return $product->suppliers()
            ->orderBy('unit_price', 'asc')
            ->first();
    }

    /**
     * Calculate optimal order quantity (Economic Order Quantity)
     */
    public function computeOrderQuantity(Product $product): int
    {
        $baseQuantity = $product->reorder_quantity ?? 100;
        $minOrderQuantity = $product->min_order_quantity ?? 10;
        $maxOrderQuantity = $product->max_order_quantity ?? 1000;

        // Apply seasonal multiplier
        $seasonalFactor = $this->getSeasonalAdjustmentFactor($product);
        $quantity = (int) ceil($baseQuantity * $seasonalFactor);

        // Ensure within bounds
        return max($minOrderQuantity, min($maxOrderQuantity, $quantity));
    }

    /**
     * Calculate expected delivery date based on supplier lead time
     */
    public function computeExpectedDeliveryDate($supplier): Carbon
    {
        $leadTimeDays = $supplier->pivot->lead_time_days ?? 14;
        return now()->addDays($leadTimeDays);
    }

    /**
     * Get current reorder status for product
     */
    public function getReorderStatus(Product $product): array
    {
        $currentStock = $product->quantity_on_hand;
        $reorderPoint = $this->computeReorderPoint($product);
        $safetyStock = $product->safety_stock ?? 20;

        $status = match (true) {
            $currentStock <= $safetyStock => 'critical',
            $currentStock <= $reorderPoint => 'needs_reorder',
            $currentStock <= ($reorderPoint * 1.5) => 'monitor',
            default => 'ok',
        };

        return [
            'current_stock' => $currentStock,
            'reorder_point' => $reorderPoint,
            'safety_stock' => $safetyStock,
            'status' => $status,
            'days_to_stockout' => $this->estimateDaysToStockout($product),
            'should_reorder' => $this->shouldReorder($product),
            'seasonal_factor' => $this->getSeasonalAdjustmentFactor($product),
        ];
    }

    /**
     * Estimate days until stockout at current consumption rate
     */
    public function estimateDaysToStockout(Product $product): ?int
    {
        // Calculate average daily consumption from last 30 days
        $dailyConsumption = DB::table('inventory_movements')
            ->where('product_id', $product->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) / 30 as avg_daily')
            ->value('avg_daily');

        if (!$dailyConsumption || $dailyConsumption == 0) {
            return null;
        }

        $currentStock = $product->quantity_on_hand;
        $safetyStock = $product->safety_stock ?? 20;

        return (int) ceil(($currentStock - $safetyStock) / $dailyConsumption);
    }

    /**
     * Get reorder recommendations for dashboard
     */
    public function getReorderRecommendations(int $tenantId, int $limit = 10): array
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->where('status', 'active')
            ->get()
            ->map(function (Product $product) {
                $status = $this->getReorderStatus($product);
                return array_merge($status, [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                ]);
            })
            ->filter(fn($item) => in_array($item['status'], ['critical', 'needs_reorder']))
            ->sortBy('days_to_stockout')
            ->take($limit)
            ->values()
            ->toArray();

        return [
            'count' => count($products),
            'items' => $products,
            'urgent' => count(array_filter($products, fn($p) => $p['status'] === 'critical')),
        ];
    }
}
