<?php

namespace Modules\HR\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\app\Models\EmployeeSkill;

class EmployeeSkillFactory extends Factory
{
    protected $model = EmployeeSkill::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'skill_id' => fake()->word(),
            'level' => fake()->word(),
            'certified_at' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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