<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\KbPortalArticle;

class KbPortalArticleFactory extends Factory
{
    protected $model = KbPortalArticle::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'category_id' => fake()->word(),
            'author_id' => fake()->word(),
            'title' => fake()->word(),
            'content' => fake()->word(),
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