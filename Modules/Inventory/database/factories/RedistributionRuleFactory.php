<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\RedistributionRule;

/** @extends Factory<RedistributionRule> */
class RedistributionRuleFactory extends Factory
{
    protected $model = RedistributionRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Min Stock Rule - '.fake()->word(),
            'from_warehouse_id' => null,
            'to_warehouse_id' => null,
            'product_id' => null,
            'rule_type' => 'min_stock',
            'trigger_threshold' => fake()->randomFloat(2, 50, 200),
            'transfer_quantity' => fake()->randomFloat(2, 100, 500),
            'is_active' => true,
            'priority' => fake()->numberBetween(1, 5),
        ];
    }
}
