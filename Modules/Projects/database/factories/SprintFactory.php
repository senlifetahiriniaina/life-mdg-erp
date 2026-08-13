<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Sprint;

/** @extends Factory<Sprint> */
class SprintFactory extends Factory
{
    protected $model = Sprint::class;

    public function definition(): array
    {
        return [
            'project_id' => ProjectFactory::new(),
            'name' => 'Sprint '.fake()->numberBetween(1, 20),
            'goal' => fake()->optional()->sentence(),
            'start_date' => null,
            'end_date' => null,
            'status' => 'planning',
            'capacity_points' => fake()->optional()->numberBetween(10, 50),
        ];
    }
}
