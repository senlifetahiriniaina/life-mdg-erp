<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeSkill;
use Modules\HR\Models\Skill;

class EmployeeSkillFactory extends Factory
{
    protected $model = EmployeeSkill::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->id,
            'skill_id' => fn () => Skill::factory()->create()->id,
            'level' => fake()->numberBetween(1, 5),
            'certified_at' => fake()->boolean(50) ? fake()->dateTimeBetween('-2 years', 'now') : null,
            'expires_at' => null,
        ];
    }
}
