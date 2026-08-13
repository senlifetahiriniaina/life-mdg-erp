<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\SequenceEnrollment;

class SequenceEnrollmentFactory extends Factory
{
    protected $model = SequenceEnrollment::class;

    public function definition(): array
    {
        return [
            'sequence_id' => EmailSequence::factory(),
            'contact_id' => Contact::factory(),
            'current_step' => 0,
            'status' => 'active',
            'enrolled_at' => now(),
            'completed_at' => null,
            'next_send_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(['status' => 'unsubscribed']);
    }
}
