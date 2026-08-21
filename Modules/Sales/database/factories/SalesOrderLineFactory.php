<?php

declare(strict_types=1);

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Chantier 32.16 (Sales deep 14-layer audit): same scaffold-boilerplate
 * pattern as SalesOrderFactory — fake()->word() on unit_price/tax_rate/
 * line_total (all decimals) and sales_order_id/product_id (FKs) — rewritten
 * to match the model's real $fillable/$casts and to actually create a real
 * parent SalesOrder by default (a line with no real parent would violate
 * sales_order_lines' own cascadeOnDelete FK constraint the moment it was
 * ever persisted, since sales_order_id is NOT NULL with no default).
 */
class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    public function definition(): array
    {
        $quantity  = fake()->randomFloat(3, 1, 50);
        $unitPrice = fake()->randomFloat(4, 500, 50000);

        return [
            'sales_order_id'   => SalesOrder::factory(),
            'product_id'       => null,
            'description'      => fake()->words(3, true),
            'quantity'         => $quantity,
            'unit_price'       => $unitPrice,
            'discount_percent' => 0,
            'tax_rate'         => 0,
            'line_total'       => round($quantity * $unitPrice, 2),
        ];
    }
}
