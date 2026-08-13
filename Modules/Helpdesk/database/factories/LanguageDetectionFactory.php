<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\LanguageDetection;

class LanguageDetectionFactory extends Factory
{
    protected $model = LanguageDetection::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'detected_language' => fake()->word(),
            'confidence' => fake()->word(),
            'original_text_language' => fake()->word(),
            'supported_language' => fake()->word(),
            'requires_translation' => fake()->word(),
            'translation_provider' => fake()->word(),
            'translated_text' => fake()->word(),
            'translation_status' => fake()->word(),
            'translation_confidence' => fake()->word(),
            'language_alternatives' => fake()->word(),
            'detection_notes' => fake()->word(),
            'detected_at' => fake()->word(),
            'translated_at' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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