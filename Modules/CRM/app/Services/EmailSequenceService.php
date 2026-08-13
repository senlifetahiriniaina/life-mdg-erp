<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Validator;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\SequenceEnrollment;
use Modules\CRM\Models\SequenceStep;

class EmailSequenceService
{
    public function createSequence(array $data): EmailSequence
    {
        return EmailSequence::create($data);
    }

    /**
     * Create a sequence after validating required fields.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(array $data): EmailSequence
    {
        Validator::make($data, [
            'name' => 'required|string',
        ])->validate();

        return EmailSequence::create($data);
    }

    public function update(EmailSequence $sequence, array $data): EmailSequence
    {
        $sequence->update($data);

        return $sequence;
    }

    public function delete(EmailSequence $sequence): bool
    {
        return (bool) $sequence->delete();
    }

    public function getWithSteps(int $sequenceId): EmailSequence
    {
        return EmailSequence::with('steps')->findOrFail($sequenceId);
    }

    public function enable(EmailSequence $sequence): bool
    {
        $sequence->enabled = true;

        return $sequence->save();
    }

    public function disable(EmailSequence $sequence): bool
    {
        $sequence->enabled = false;

        return $sequence->save();
    }

    /**
     * @throws \Exception when a step with the same step_order already exists
     */
    public function addStep(EmailSequence $sequence, array $data): SequenceStep
    {
        if (isset($data['step_order'])
            && $sequence->steps()->where('step_order', $data['step_order'])->exists()) {
            throw new \Exception("Step order {$data['step_order']} already exists for this sequence.");
        }

        $maxOrder = $sequence->steps()->max('order') ?? 0;

        $data['sequence_id'] = $sequence->id;
        $data['order'] = $data['order'] ?? ($maxOrder + 1);

        return SequenceStep::create($data);
    }

    public function enroll(EmailSequence $sequence, int $contactId): SequenceEnrollment
    {
        $existing = SequenceEnrollment::where('sequence_id', $sequence->id)
            ->where('contact_id', $contactId)
            ->whereIn('status', ['active', 'paused'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $firstStep = $sequence->steps()->orderBy('order')->first();
        $nextSendAt = $firstStep ? now()->addDays($firstStep->delay_days) : null;

        return SequenceEnrollment::create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contactId,
            'current_step' => 0,
            'status' => 'active',
            'enrolled_at' => now(),
            'next_send_at' => $nextSendAt,
        ]);
    }

    public function processEnrollment(SequenceEnrollment $enrollment): bool
    {
        if (! $enrollment->isActive()) {
            return false;
        }

        $enrollment->advance();

        return true;
    }

    public function processDueEnrollments(): int
    {
        $count = 0;

        SequenceEnrollment::where('status', 'active')
            ->where('next_send_at', '<=', now())
            ->with('sequence.steps')
            ->each(function (SequenceEnrollment $enrollment) use (&$count): void {
                if ($this->processEnrollment($enrollment)) {
                    $count++;
                }
            });

        return $count;
    }

    public function unenroll(SequenceEnrollment $enrollment): void
    {
        $enrollment->unsubscribe();
    }

    /**
     * @return array{total_enrollments: int, active: int, completed: int, unsubscribed: int, completion_rate: float}
     */
    public function getSequenceStats(EmailSequence $sequence): array
    {
        $total = $sequence->enrollments()->count();
        $active = $sequence->enrollments()->where('status', 'active')->count();
        $completed = $sequence->enrollments()->where('status', 'completed')->count();
        $unsubscribed = $sequence->enrollments()->where('status', 'unsubscribed')->count();

        return [
            'total_enrollments' => $total,
            'active' => $active,
            'completed' => $completed,
            'unsubscribed' => $unsubscribed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
        ];
    }
}
