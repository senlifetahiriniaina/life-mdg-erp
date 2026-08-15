<?php

namespace Modules\Validation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;

class ApprovalRuleFactory extends Factory
{
    protected $model = ApprovalRule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'workflow_id' => ApprovalWorkflow::factory(),
            'rule_order' => fake()->numberBetween(1, 10),
            'condition_type' => fake()->randomElement(ApprovalRule::CONDITION_TYPES),
            'condition_operator' => fake()->randomElement(ApprovalRule::OPERATORS),
            'condition_value' => (string) fake()->numberBetween(1000, 500000),
            'required_approvers_count' => fake()->numberBetween(1, 5),
            'approval_mode' => fake()->randomElement(['sequential', 'parallel']),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
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
