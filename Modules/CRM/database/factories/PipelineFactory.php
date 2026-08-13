<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Pipeline;

class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Pipeline',
            'is_default' => false,
            'stages' => ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won'],
        ];
    }
}
