<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\TransformationRule;

class TransformationRuleFactory extends Factory
{
    protected $model = TransformationRule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'source_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'rule_order' => fake()->word(),
            'rule_type' => fake()->word(),
            'rule_config' => fake()->word(),
            'is_active' => true,
            'title' => fake()->word(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}