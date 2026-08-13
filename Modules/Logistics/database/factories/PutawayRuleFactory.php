<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Location;
use Modules\Logistics\Models\PutawayRule;

/** @extends Factory<PutawayRule> */
class PutawayRuleFactory extends Factory
{
    protected $model = PutawayRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Rule',
            'priority' => fake()->numberBetween(1, 200),
            'product_id' => null,
            'product_category_id' => null,
            'location_id' => Location::factory(),
            'strategy' => fake()->randomElement(['fixed', 'nearest_empty', 'highest_capacity', 'fifo', 'fefo']),
            'temperature_required' => null,
            'is_active' => true,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => ['strategy' => 'fixed']);
    }

    public function fefo(): static
    {
        return $this->state(fn (array $attributes) => ['strategy' => 'fefo']);
    }

    public function cold(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature_required' => fake()->randomElement(['chilled', 'frozen']),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
