<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\WebFormSubmission;

class WebFormSubmissionFactory extends Factory
{
    protected $model = WebFormSubmission::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'form_id' => fake()->word(),
            'data' => fake()->word(),
            'lead_id' => fake()->word(),
            'ip_address' => fake()->word(),
            'user_agent' => fake()->word(),
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