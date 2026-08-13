<?php

namespace Modules\CRM\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\app\Models\OpportunityHistory;

class OpportunityHistoryFactory extends Factory
{
    protected $model = OpportunityHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'opportunity_id' => fake()->word(),
            'field' => fake()->word(),
            'old_value' => fake()->word(),
            'new_value' => fake()->word(),
            'changed_by' => fake()->word(),
            'changed_at' => fake()->word(),
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