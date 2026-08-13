<?php

namespace Modules\CRM\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\app\Models\TerritoryAlert;

class TerritoryAlertFactory extends Factory
{
    protected $model = TerritoryAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'territory_id' => fake()->word(),
            'alert_type' => fake()->word(),
            'severity' => fake()->word(),
            'title' => fake()->word(),
            'message' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'assigned_to' => fake()->word(),
            'triggered_at' => fake()->word(),
            'resolved_at' => fake()->word(),
            'metadata' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
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