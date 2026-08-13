<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\CampaignEnrollment;

class CampaignEnrollmentFactory extends Factory
{
    protected $model = CampaignEnrollment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'campaign_id' => fake()->word(),
            'enrollable_type' => fake()->word(),
            'enrollable_id' => fake()->word(),
            'current_stage' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'enrolled_at' => fake()->word(),
            'completed_at' => fake()->word(),
            'email_opens' => fake()->word(),
            'email_clicks' => fake()->word(),
            'sms_reads' => fake()->word(),
            'interactions' => fake()->word(),
            'metadata' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}