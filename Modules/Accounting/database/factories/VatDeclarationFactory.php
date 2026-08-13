<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\VatDeclaration;

class VatDeclarationFactory extends Factory
{
    protected $model = VatDeclaration::class;

    public function definition(): array
    {
        $totalSales = $this->faker->randomFloat(2, 10000, 500000);
        $totalPurchases = $this->faker->randomFloat(2, 5000, 200000);
        $vatRate = 0.2;

        return [
            'period_type' => $this->faker->randomElement(['monthly', 'quarterly', 'annual']),
            'period_year' => 2026,
            'period_number' => $this->faker->numberBetween(1, 12),
            'status' => $this->faker->randomElement(['draft', 'submitted', 'approved']),
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'vat_collected' => round($totalSales * $vatRate, 2),
            'vat_deductible' => round($totalPurchases * $vatRate, 2),
            'vat_due' => round(($totalSales - $totalPurchases) * $vatRate, 2),
            'submitted_at' => null,
            'reference' => null,
        ];
    }
}
