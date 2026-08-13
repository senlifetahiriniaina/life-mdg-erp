<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\Cache;

class StockManagementService
{
    const CACHE_TTL = 3600;

    /**
     * Add stock to inventory
     */
    public function addStock(int $productId, int $quantity, array $metadata = []): array
    {
        $cacheKey = "stock:{$productId}";

        $stock = Cache::get($cacheKey, [
            'product_id' => $productId,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
        ]);

        $stock['quantity'] += $quantity;
        $stock['available'] = $stock['quantity'] - $stock['reserved'];
        $stock['updated_at'] = now()->toIso8601String();

        Cache::put($cacheKey, $stock, self::CACHE_TTL);

        return [
            'product_id' => $productId,
            'added_quantity' => $quantity,
            'total_quantity' => $stock['quantity'],
            'available_quantity' => $stock['available'],
            'status' => 'added',
        ];
    }

    /**
     * Remove stock from inventory
     */
    public function removeStock(int $productId, int $quantity, string $reason = 'manual'): array
    {
        $cacheKey = "stock:{$productId}";

        $stock = Cache::get($cacheKey);
        if (!$stock || $stock['available'] < $quantity) {
            return ['error' => 'Insufficient stock available'];
        }

        $stock['quantity'] -= $quantity;
        $stock['available'] = $stock['quantity'] - $stock['reserved'];
        $stock['updated_at'] = now()->toIso8601String();

        Cache::put($cacheKey, $stock, self::CACHE_TTL);

        return [
            'product_id' => $productId,
            'removed_quantity' => $quantity,
            'remaining_quantity' => $stock['quantity'],
            'available_quantity' => $stock['available'],
            'reason' => $reason,
            'status' => 'removed',
        ];
    }

    /**
     * Reserve stock for order
     */
    public function reserveStock(int $orderId, array $items): array
    {
        $reservations = [];
        $failedReservations = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $cacheKey = "stock:{$productId}";
            $stock = Cache::get($cacheKey);

            if (!$stock || ($stock['available'] < $quantity)) {
                $failedReservations[] = [
                    'product_id' => $productId,
                    'requested' => $quantity,
                    'available' => $stock['available'] ?? 0,
                ];
                continue;
            }

            $stock['reserved'] += $quantity;
            $stock['available'] = $stock['quantity'] - $stock['reserved'];
            Cache::put($cacheKey, $stock, self::CACHE_TTL);

            $reservations[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'status' => 'reserved',
            ];
        }

        return [
            'order_id' => $orderId,
            'reserved_items' => count($reservations),
            'failed_items' => count($failedReservations),
            'reservations' => $reservations,
            'failed_reservations' => $failedReservations,
        ];
    }

    /**
     * Release reserved stock
     */
    public function releaseReservedStock(int $orderId, array $items): array
    {
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $cacheKey = "stock:{$productId}";
            $stock = Cache::get($cacheKey);

            if ($stock) {
                $stock['reserved'] -= $quantity;
                $stock['available'] = $stock['quantity'] - $stock['reserved'];
                Cache::put($cacheKey, $stock, self::CACHE_TTL);
            }
        }

        return [
            'order_id' => $orderId,
            'status' => 'released',
            'items_count' => count($items),
        ];
    }

    /**
     * Get current stock levels
     */
    public function getStockLevels(array $productIds): array
    {
        $stockLevels = [];

        foreach ($productIds as $productId) {
            $cacheKey = "stock:{$productId}";
            $stock = Cache::get($cacheKey, [
                'product_id' => $productId,
                'quantity' => 0,
                'reserved' => 0,
                'available' => 0,
            ]);

            $stockLevels[$productId] = [
                'product_id' => $productId,
                'total_quantity' => $stock['quantity'],
                'reserved_quantity' => $stock['reserved'],
                'available_quantity' => $stock['available'],
                'status' => $this->getStockStatus($stock['available']),
            ];
        }

        return $stockLevels;
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(int $threshold = 10): array
    {
        $alerts = [];
        // Query all products with low stock
        $products = []; // Get from database

        foreach ($products as $product) {
            $cacheKey = "stock:{$product['id']}";
            $stock = Cache::get($cacheKey);

            if ($stock && $stock['available'] <= $threshold) {
                $alerts[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'current_stock' => $stock['available'],
                    'threshold' => $threshold,
                    'urgency' => $stock['available'] === 0 ? 'critical' : 'warning',
                ];
            }
        }

        return [
            'total_alerts' => count($alerts),
            'alerts' => $alerts,
        ];
    }

    /**
     * Get stock status
     */
    private function getStockStatus(int $available): string
    {
        if ($available === 0) {
            return 'out_of_stock';
        } elseif ($available <= 10) {
            return 'low_stock';
        } elseif ($available > 100) {
            return 'overstock';
        }

        return 'normal';
    }
}
