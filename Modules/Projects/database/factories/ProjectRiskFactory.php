<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectRisk;

class ProjectRiskFactory extends Factory
{
    protected $model = ProjectRisk::class;

    public function definition(): array
    {
        $level = fake()->randomElement(['low', 'medium', 'high']);
        $scoreMap = ['low' => 1, 'medium' => 2, 'high' => 3];

        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['open', 'in_review', 'mitigated', 'closed']),
            'probability' => $level,
            'impact' => $level,
            'probability_score' => $scoreMap[$level],
            'impact_score' => $scoreMap[$level],
            'mitigation_plan' => fake()->optional()->sentence(),
            'owner_id' => User::factory(),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
        ];
    }
}
