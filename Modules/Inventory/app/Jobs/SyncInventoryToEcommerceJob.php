<?php

declare(strict_types=1);

namespace Modules\Inventory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\EcommerceSyncService;

class SyncInventoryToEcommerceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly ?int $productId = null) {}

    public function handle(EcommerceSyncService $service): void
    {
        if ($this->productId !== null) {
            $product = Product::find($this->productId);
            if ($product instanceof Product) {
                $service->syncProductToEcommerce($product);
            }

            return;
        }

        $service->syncAllProducts();
    }
}
