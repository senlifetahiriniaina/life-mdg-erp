<?php

namespace Modules\CRM\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\app\Models\CampaignAction;

class CampaignActionFactory extends Factory
{
    protected $model = CampaignAction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'campaign_id' => fake()->word(),
            'enrollment_id' => fake()->word(),
            'action_type' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'payload' => fake()->word(),
            'scheduled_at' => fake()->word(),
            'executed_at' => fake()->word(),
            'error_message' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
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