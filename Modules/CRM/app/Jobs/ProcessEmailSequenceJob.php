<?php

declare(strict_types=1);

namespace Modules\CRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CRM\Models\EmailSequenceEnrollment;
use Modules\CRM\Services\EmailSequenceService;

class ProcessEmailSequenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(private readonly int $enrollmentId) {}

    public function handle(EmailSequenceService $service): void
    {
        $enrollment = EmailSequenceEnrollment::find($this->enrollmentId);

        if (! $enrollment) {
            return;
        }

        $service->processStep($enrollment);
    }
}
