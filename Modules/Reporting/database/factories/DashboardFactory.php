<?php

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Models\Dashboard;

class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    /**
     * Define the model's default state.
     *
     * Chantier 19 (Lot 5): scaffold boilerplate — fake()->word() on a
     * unsignedBigInteger `tenant_id` and a boolean `is_default` column, and
     * no `created_by` at all despite that column being NOT NULL on a fresh
     * install before this same chantier's migration relaxed it — rewritten
     * to match Dashboard's real $fillable/schema.
     */
    public function definition(): array
    {
        return [
            'tenant_id'   => fake()->numberBetween(1, 1000),
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_default'  => false,
            'is_public'   => false,
            'layout'      => ['columns' => 4, 'rows' => 3],
            'created_by'  => null,
            'shared_with' => [],
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