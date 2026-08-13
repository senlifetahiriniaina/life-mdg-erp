<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\AutomationRule;

/** @extends Factory<AutomationRule> */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'project_id' => ProjectFactory::new(),
            'name' => fake()->sentence(3),
            'trigger' => fake()->randomElement(['task_created', 'task_status_changed', 'task_assigned', 'comment_added']),
            'conditions' => [],
            'actions' => [['type' => 'change_status', 'value' => 'in_progress']],
            'active' => true,
        ];
    }
}
