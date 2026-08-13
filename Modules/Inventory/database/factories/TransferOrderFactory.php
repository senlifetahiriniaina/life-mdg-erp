<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\Warehouse;

/** @extends Factory<TransferOrder> */
class TransferOrderFactory extends Factory
{
    protected $model = TransferOrder::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'reference' => 'TXFR-2026-'.str_pad((string) $seq++, 5, '0', STR_PAD_LEFT),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
            'type' => 'manual',
            'priority' => 'normal',
            'requested_by' => null,
            'approved_by' => null,
            'expected_delivery_date' => now()->addDays(fake()->numberBetween(2, 14)),
            'total_items' => 0,
            'total_value' => 0,
        ];
    }
}
