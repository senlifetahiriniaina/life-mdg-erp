<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\PipelineSnapshot;

class PipelineSnapshotFactory extends Factory
{
    protected $model = PipelineSnapshot::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => fake()->numberBetween(1, 10),
            'snapshot_date' => now()->toDateString(),
            'total_value' => 0,
            'deal_count' => 0,
            'avg_deal_size' => 0,
            'stage_data' => [['stage' => 'prospecting', 'count' => 5, 'value' => 50000]],
        ];
    }
}
