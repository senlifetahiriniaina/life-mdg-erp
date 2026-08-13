<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\EmailSequence;

class EmailSequenceFactory extends Factory
{
    protected $model = EmailSequence::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'status' => 'draft',
            'trigger_type' => 'manual',
            'trigger_config' => null,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }
}
