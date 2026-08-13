<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\ProjectBilling;

/** @extends Factory<ProjectBilling> */
class ProjectBillingFactory extends Factory
{
    protected $model = ProjectBilling::class;

    public function definition(): array
    {
        return [
            'project_id' => ProjectFactory::new(),
            'billing_type' => 'hourly',
            'hourly_rate' => fake()->randomFloat(2, 50, 200),
            'budget_hours' => fake()->optional()->randomFloat(2, 10, 500),
            'budget_amount' => fake()->optional()->randomFloat(2, 1000, 50000),
            'total_billed' => 0,
            'total_hours' => 0,
            'status' => 'active',
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'fixed',
        ]);
    }

    public function milestone(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'milestone',
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
