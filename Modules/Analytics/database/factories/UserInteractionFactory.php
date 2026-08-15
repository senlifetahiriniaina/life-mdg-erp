<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\UserInteraction;

class UserInteractionFactory extends Factory
{
    protected $model = UserInteraction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_type' => fake()->word(),
            'user_id' => fake()->numberBetween(1, 1000),
            'interacted_item_type' => fake()->word(),
            'interacted_item_id' => fake()->numberBetween(1, 1000),
            'interaction_type' => fake()->randomElement(['view', 'click', 'purchase', 'like', 'share']),
            'engagement_score' => fake()->randomFloat(4, 0, 1),
            'context' => [
                'source' => fake()->randomElement(['web', 'mobile', 'api']),
            ],
            'interacted_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}
