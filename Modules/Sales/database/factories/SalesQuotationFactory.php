<?php

declare(strict_types=1);

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\SalesQuotation;

/**
 * Chantier 32.16 (Sales deep 14-layer audit): same scaffold-boilerplate
 * pattern as SalesOrderFactory — fake()->word() on tenant_id/contact_id/
 * total/valid_until/created_by, and a status enum ('draft'/'published'/
 * 'archived') that doesn't match any real SalesQuotation status
 * ('draft'/'sent'/'accepted' — see SalesQuotation::isConvertible()).
 * Rewritten to match the model's real $fillable/$casts and status
 * vocabulary.
 */
class SalesQuotationFactory extends Factory
{
    protected $model = SalesQuotation::class;

    public function definition(): array
    {
        return [
            'tenant_id'             => 1,
            // uniqid() rather than fake()->unique() — see SalesOrderFactory's
            // equivalent comment (parallel-worker collision risk against the
            // real DB unique() constraint on this column).
            'reference'             => 'QT-' . now()->format('Y') . '-' . strtoupper(uniqid('', true)),
            'contact_id'            => null,
            'account_id'            => null,
            'status'                => 'draft',
            'currency'              => 'MGA',
            'total'                 => fake()->randomFloat(2, 1000, 500000),
            'valid_until'           => now()->addDays(30)->toDateString(),
            'notes'                 => fake()->boolean(30) ? fake()->sentence() : null,
            'converted_to_order_id' => null,
            'created_by'            => 1,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'sent']);
    }
}
