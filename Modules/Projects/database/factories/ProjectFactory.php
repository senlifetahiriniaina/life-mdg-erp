<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Project;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->lexify('PRJ-????')),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'start_date' => null,
            'end_date' => null,
            'budget' => null,
            'currency' => 'USD',
            'color' => '#3B82F6',
            'is_billable' => false,
        ];
    }
}
