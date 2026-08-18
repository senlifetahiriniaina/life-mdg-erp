<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTeamMember;

class ProjectTeamMemberFactory extends Factory
{
    protected $model = ProjectTeamMember::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['manager', 'member', 'viewer']),
            'can_edit_tasks' => fake()->boolean(70),
            'can_manage_members' => fake()->boolean(20),
            'joined_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'left_at' => null,
        ];
    }
}
