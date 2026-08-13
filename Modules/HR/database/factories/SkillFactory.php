<?php

declare(strict_types=1);

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Skill;

class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        $categories = ['Backend', 'Frontend', 'DevOps', 'Management', 'Analytics', 'Communication'];

        return [
            'name' => $this->faker->unique()->word(),
            'category' => $this->faker->randomElement($categories),
            'description' => $this->faker->sentence(),
        ];
    }
}
