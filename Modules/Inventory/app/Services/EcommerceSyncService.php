<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Events\StockChangedForEcommerce;
use Modules\Inventory\Jobs\SyncInventoryToEcommerceJob;
use Modules\Inventory\Models\Product;

class EcommerceSyncService
{
    public function syncProductToEcommerce(Product $product): void
    {
        // Fire event for Ecommerce module to handle
        $totalStock = $product->stock->sum('quantity');

        StockChangedForEcommerce::dispatch($product, (float) $totalStock);

        $product->update([
            'ecommerce_synced_at' => now(),
            'ecommerce_sync_pending' => false,
        ]);
    }

    public function syncStockChange(Product $product, float $newQty): void
    {
        StockChangedForEcommerce::dispatch($product, $newQty);

        $product->update([
            'ecommerce_synced_at' => now(),
            'ecommerce_sync_pending' => false,
        ]);
    }

    public function syncAllProducts(): void
    {
        Product::where('is_active', true)
            ->with('stock')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $this->syncProductToEcommerce($product);
                }
            });
    }

    public function queueSyncAll(): void
    {
        SyncInventoryToEcommerceJob::dispatch();
    }

    public function getSyncStatus(): array
    {
        $lastSync = Product::whereNotNull('ecommerce_synced_at')
            ->orderByDesc('ecommerce_synced_at')
            ->value('ecommerce_synced_at');

        $pendingCount = Product::where('ecommerce_sync_pending', true)->count();

        return [
            'last_sync_at' => $lastSync,
            'pending_count' => $pendingCount,
        ];
    }
}
