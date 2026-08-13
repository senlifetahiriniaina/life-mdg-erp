<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Quote;
use Modules\CRM\Models\QuoteLine;

class QuoteLineFactory extends Factory
{
    protected $model = QuoteLine::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 5000);
        $discountPct = fake()->randomFloat(2, 0, 20);
        $lineTotal = $quantity * $unitPrice * (1 - $discountPct / 100);

        return [
            'quote_id' => Quote::factory(),
            'product_bundle_id' => null,
            'description' => fake()->words(4, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_pct' => $discountPct,
            'line_total' => $lineTotal,
        ];
    }
}
