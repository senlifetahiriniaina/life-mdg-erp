<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Mail;
use Modules\CRM\Jobs\ProcessSequenceStepJob;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\EmailSequenceEnrollment;
use Modules\CRM\Models\Lead;

/**
 * @deprecated Use EmailSequenceService instead. Kept for backward compatibility.
 */
class SequenceService
{
    public function enroll(EmailSequence $sequence, Contact|Lead $subject): EmailSequenceEnrollment
    {
        $isContact = $subject instanceof Contact;

        $existing = EmailSequenceEnrollment::where('sequence_id', $sequence->id)
            ->when($isContact, fn ($q) => $q->where('contact_id', $subject->id))
            ->whereIn('status', ['active', 'paused'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $firstStep = $sequence->steps->first();
        // Support both delay_hours (legacy) and delay_days (new)
        /** @var int $delayHours */
        $delayHours = isset($firstStep) ? ($firstStep->getAttribute('delay_hours') ?? ($firstStep->delay_days * 24)) : 0;
        $nextAt = $firstStep ? now()->addHours($delayHours) : null;

        $enrollment = EmailSequenceEnrollment::create([
            'sequence_id' => $sequence->id,
            'contact_id' => $isContact ? $subject->id : null,
            'current_step' => 0,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        if ($nextAt) {
            ProcessSequenceStepJob::dispatch($enrollment->id)->delay($nextAt);
        }

        return $enrollment;
    }

    public function processStep(EmailSequenceEnrollment $enrollment): void
    {
        $enrollment->load('sequence.steps', 'contact');
        $sequence = $enrollment->sequence;

        if ($enrollment->status !== 'active') {
            return;
        }

        $nextStepOrder = $enrollment->current_step + 1;
        $step = $sequence->steps->firstWhere('step_order', $nextStepOrder);

        if (! $step) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

            return;
        }

        $contact = $enrollment->contact;
        $email = $contact?->email ?? null;
        $name = $contact ? trim("{$contact->first_name} {$contact->last_name}") : 'there';

        if ($email) {
            Mail::html($step->body_html, function ($msg) use ($step, $email, $name) {
                $msg->to($email, $name)->subject($step->subject);
            });
        }

        $enrollment->increment('current_step');

        $nextStep = $sequence->steps->firstWhere('step_order', $nextStepOrder + 1);

        if ($nextStep) {
            /** @var int $nextDelay */
            $nextDelay = $nextStep->getAttribute('delay_hours') ?? ($nextStep->delay_days * 24);
            $nextAt = now()->addHours($nextDelay);
            ProcessSequenceStepJob::dispatch($enrollment->id)->delay($nextAt);
        } else {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    public function unenroll(EmailSequenceEnrollment $enrollment): void
    {
        $enrollment->update(['status' => 'unsubscribed']);
    }
}
