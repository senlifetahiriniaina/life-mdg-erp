<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\EmailSequenceStep;

class EmailSequenceStepFactory extends Factory
{
    protected $model = EmailSequenceStep::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
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