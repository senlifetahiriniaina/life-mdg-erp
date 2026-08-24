<?php

declare(strict_types=1);

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\SalesOrder;

/**
 * Chantier 32.16 (Sales deep 14-layer audit): this factory was scaffold
 * boilerplate — fake()->word() on every typed/FK/date column, and a
 * status enum ('draft'/'published'/'archived') that doesn't match any real
 * SalesOrder status ('draft'/'confirmed'/'processing'/'shipped'/
 * 'delivered'/'cancelled'/'returned' — see SalesOrder::isEditable()/
 * isCancellable() and SalesController::updateOrderStatus()'s transition
 * table). Confirmed broken by actually calling SalesOrder::factory()
 * ->create(): a fatal Carbon\Exceptions\InvalidFormatException trying to
 * parse a fake word as expected_delivery_date — this factory has never
 * been usable in its default state, and zero test in this module ever
 * called it (grep-confirmed), matching the exact "confirmed dead until
 * deep-audited" pattern already documented dozens of times this session
 * for other modules' scaffold factories. Rewritten to match the model's
 * real $fillable/$casts and real status vocabulary.
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id'               => 1,
            // uniqid() rather than fake()->unique() — the latter's uniqueness
            // is only guaranteed within one Faker instance/process, not
            // across Pest's parallel worker processes, which could collide
            // on sales_orders.reference's real unique() DB constraint.
            'reference'               => 'SO-' . now()->format('Y') . '-' . strtoupper(uniqid('', true)),
            'contact_id'              => null,
            'account_id'              => null,
            'opportunity_id'          => null,
            'status'                  => 'draft',
            'currency'                => 'MGA',
            'subtotal'                => fake()->randomFloat(2, 1000, 500000),
            'discount_amount'         => 0,
            'tax_amount'              => 0,
            'total'                   => fake()->randomFloat(2, 1000, 500000),
            'notes'                   => fake()->boolean(30) ? fake()->sentence() : null,
            'shipping_address'        => null,
            'expected_delivery_date'  => null,
            'confirmed_at'            => null,
            'cancelled_at'            => null,
            'created_by'              => 1,
            'sales_rep_id'            => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'delivered',
            'confirmed_at' => now()->subDays(3),
        ]);
    }
}
