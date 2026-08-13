<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\SequenceStep;

class SequenceStepFactory extends Factory
{
    protected $model = SequenceStep::class;

    public function definition(): array
    {
        return [
            'sequence_id' => EmailSequence::factory(),
            'order' => 1,
            'delay_days' => $this->faker->numberBetween(0, 14),
            'subject' => $this->faker->sentence(6),
            'body' => $this->faker->paragraphs(2, true),
            'from_name' => $this->faker->optional()->name(),
            'from_email' => $this->faker->optional()->safeEmail(),
        ];
    }
}
