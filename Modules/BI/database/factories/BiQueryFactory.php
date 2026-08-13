<?php

namespace Modules\BI\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\app\Models\BiQuery;

class BiQueryFactory extends Factory
{
    protected $model = BiQuery::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'created_by' => fake()->word(),
            'name' => fake()->word(),
            'sql_query' => fake()->word(),
            'datasource' => fake()->word(),
            'result_cache_ttl' => fake()->word(),
            'is_public' => fake()->word(),
            'last_run_at' => fake()->word(),
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