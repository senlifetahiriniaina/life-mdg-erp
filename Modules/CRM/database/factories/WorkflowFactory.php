<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Workflow;

class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->sentence(3),
            'description'    => $this->faker->paragraph(),
            'trigger_type'   => $this->faker->randomElement(['opportunity_created', 'contact_updated', 'manual']),
            'owner_id'       => 1,
            'status'         => 'draft',
            'execution_count' => 0,
            'success_count'  => 0,
            'failure_count'  => 0,
        ];
    }
}
