<?php

declare(strict_types=1);

namespace Modules\Inventory\Observers;

use App\Events\InventoryStockLevelChanged;
use Modules\Inventory\Models\WarehouseStock;

class WarehouseStockObserver
{
    public function updating(WarehouseStock $stock): void
    {
        $oldQuantity = $stock->getOriginal('quantity') ?? 0;

        if ($stock->quantity !== $oldQuantity) {
            // Determine reason for stock change
            $reason = 'adjustment';
            if (request()->filled('reason')) {
                $reason = request()->input('reason');
            }

            // Dispatch after save to send updated model
            $stock->saveQuietly();
            InventoryStockLevelChanged::dispatch($stock, $oldQuantity, $reason);
        }
    }

    public function created(WarehouseStock $stock): void
    {
        InventoryStockLevelChanged::dispatch($stock, 0, 'creation');
    }
}
