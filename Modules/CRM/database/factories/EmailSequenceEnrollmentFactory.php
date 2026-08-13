<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\EmailSequenceEnrollment;

class EmailSequenceEnrollmentFactory extends Factory
{
    protected $model = EmailSequenceEnrollment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'sequence_id' => fake()->word(),
            'contact_id' => fake()->word(),
            'current_step' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'enrolled_at' => fake()->word(),
            'completed_at' => fake()->word(),
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