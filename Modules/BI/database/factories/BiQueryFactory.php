<?php

namespace Modules\BI\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\BiQuery;

class BiQueryFactory extends Factory
{
    protected $model = BiQuery::class;

    /**
     * Define the model's default state.
     *
     * Chantier 32.24 (BI 14-layer audit, layer 5 — format de données): this was
     * scaffold boilerplate using `fake()->word()` for every field regardless of
     * type, matching the exact pattern documented repeatedly elsewhere this
     * session — `last_run_at` (a real `datetime` cast) received a random word
     * like "natus", which Carbon cannot parse at all, a guaranteed fatal error
     * the moment any test/code actually read a hydrated `last_run_at`. Rewritten
     * to match the model's real `$fillable`/`$casts`.
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'sql_query' => 'SELECT '.fake()->numberBetween(1, 100).' as value',
            'datasource' => fake()->randomElement(['internal', 'mysql', 'postgresql', 'rest_api']),
            'result_cache_ttl' => fake()->numberBetween(0, 3600),
            'is_public' => fake()->boolean(),
            'last_run_at' => null,
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
