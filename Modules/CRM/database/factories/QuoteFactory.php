<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Quote;

class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 50000);
        $discountAmount = fake()->randomFloat(2, 0, $subtotal * 0.1);
        $taxAmount = ($subtotal - $discountAmount) * 0.20;
        $total = $subtotal - $discountAmount + $taxAmount;

        return [
            'opportunity_id' => null,
            'contact_id' => null,
            'reference' => 'QT-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => fake()->randomElement(['draft', 'sent', 'accepted', 'rejected', 'expired']),
            'valid_until' => fake()->dateTimeBetween('now', '+90 days')->format('Y-m-d'),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
