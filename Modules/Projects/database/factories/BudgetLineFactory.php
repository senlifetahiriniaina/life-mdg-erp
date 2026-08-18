<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;

class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    public function definition(): array
    {
        $estimated = fake()->randomFloat(2, 100_000, 10_000_000);
        $category = fake()->randomElement(['capex', 'opex']);

        return [
            'project_id' => Project::factory(),
            'description' => fake()->sentence(4),
            'category' => $category,
            'ohada_account' => $category === 'capex' ? '2184' : '6019',
            'estimated_amount' => $estimated,
            'actual_amount' => fake()->randomFloat(2, 0, $estimated),
            'phase' => fake()->randomElement(['planning', 'execution', 'closing']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
