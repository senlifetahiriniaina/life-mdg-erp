<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SeasonalFactor;

/** @extends Factory<SeasonalFactor> */
class SeasonalFactorFactory extends Factory
{
    protected $model = SeasonalFactor::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'category_id' => null,
            'period_type' => 'monthly',
            'period_index' => fake()->numberBetween(1, 12),
            'factor' => fake()->randomFloat(2, 0.5, 2.5),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(['product_id' => null, 'category_id' => $categoryId]);
    }
}
