<?php

declare(strict_types=1);

namespace Modules\CRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CallRecording;
use Modules\CRM\Services\VoipService;

class SummarizeCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 120;

    public function __construct(public readonly int $callId) {}

    /**
     * Execute the job.
     *
     * Calls generateAiSummary via VoipService and marks the recording as ready.
     */
    public function handle(VoipService $voipService): void
    {
        $recording = CallRecording::where('call_id', $this->callId)->latest()->first();

        if (! $recording) {
            Log::warning('SummarizeCallJob: No recording found', ['call_id' => $this->callId]);

            return;
        }

        try {
            $summary = $voipService->generateAiSummary($this->callId);

            $recording->ai_summary = $summary;
            $recording->status     = CallRecording::STATUS_READY;
            $recording->save();

            Log::info('SummarizeCallJob: Summary generated', ['call_id' => $this->callId]);
        } catch (\Throwable $e) {
            Log::error('SummarizeCallJob: Failed to generate summary', [
                'call_id' => $this->callId,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
