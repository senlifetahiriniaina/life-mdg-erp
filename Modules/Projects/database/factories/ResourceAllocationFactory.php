<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\ResourceAllocation;

/** @extends Factory<ResourceAllocation> */
class ResourceAllocationFactory extends Factory
{
    protected $model = ResourceAllocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => ProjectFactory::new(),
            'task_id' => null,
            'allocation_type' => 'part_time',
            'allocation_percent' => fake()->numberBetween(50, 100),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'hours_per_day' => 8.0,
            'actual_hours_logged' => 0,
            'status' => 'planned',
            'notes' => null,
        ];
    }
}
