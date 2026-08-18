<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Milestone;
use Modules\Projects\Models\Project;

class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        $isReached = fake()->boolean(40);

        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(3),
            'due_date' => fake()->dateTimeBetween('-1 month', '+3 months'),
            'is_reached' => $isReached,
            'reached_at' => $isReached ? fake()->dateTimeBetween('-1 month', 'now') : null,
        ];
    }
}
