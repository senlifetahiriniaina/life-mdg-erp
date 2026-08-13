<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\EncryptedField;

class EncryptedFieldFactory extends Factory
{
    protected $model = EncryptedField::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'table_name' => fake()->word(),
            'column_name' => fake()->word(),
            'encryption_algorithm' => fake()->word(),
            'encryption_key_id' => fake()->word(),
            'is_searchable' => fake()->word(),
            'metadata' => fake()->word(),
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