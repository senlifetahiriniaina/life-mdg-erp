<?php

declare(strict_types=1);

namespace Modules\Inventory\Observers;

use App\Events\InventoryProductUpdated;
use Modules\Inventory\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        InventoryProductUpdated::dispatch($product, 'created');
    }

    public function updated(Product $product): void
    {
        InventoryProductUpdated::dispatch($product, 'updated');
    }
}
