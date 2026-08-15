<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Models\SourceSchema;

class SourceSchemaFactory extends Factory
{
    protected $model = SourceSchema::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'import_job_id' => ImportJob::factory(),
            'detected_columns' => fake()->words(5),
            'detected_encoding' => fake()->randomElement(['UTF-8', 'ISO-8859-1', 'Windows-1252']),
            'detected_delimiter' => fake()->randomElement([',', ';', "\t", '|']),
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