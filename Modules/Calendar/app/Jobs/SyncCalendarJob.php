<?php

declare(strict_types=1);

namespace Modules\Calendar\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Calendar\Services\AppleCalendarService;
use Modules\Calendar\Services\GoogleCalendarService;
use Modules\Calendar\Services\ModuleEventAggregatorService;
use Modules\Calendar\Services\OutlookCalendarService;

class SyncCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    /**
     * @param string|null $provider  'google'|'outlook'|'apple'|'modules'|null (null = all)
     */
    public function __construct(
        private readonly int $userId,
        private readonly ?string $provider = null,
    ) {}

    public function handle(
        GoogleCalendarService $googleService,
        OutlookCalendarService $outlookService,
        AppleCalendarService $appleService,
        ModuleEventAggregatorService $aggregatorService,
    ): void {
        $synced = 0;

        $all = $this->provider === null;

        if ($all || $this->provider === 'google') {
            try {
                $synced += $googleService->syncFromGoogle($this->userId);
            } catch (\Throwable $e) {
                Log::warning('Google Calendar sync failed', [
                    'user_id' => $this->userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($all || $this->provider === 'outlook') {
            try {
                $synced += $outlookService->syncFromOutlook($this->userId);
            } catch (\Throwable $e) {
                Log::warning('Outlook Calendar sync failed', [
                    'user_id' => $this->userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($all || $this->provider === 'apple') {
            try {
                $synced += $appleService->syncFromApple($this->userId);
            } catch (\Throwable $e) {
                Log::warning('Apple Calendar sync failed', [
                    'user_id' => $this->userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($all || $this->provider === 'modules') {
            try {
                $synced += $aggregatorService->aggregateForUser($this->userId);
            } catch (\Throwable $e) {
                Log::warning('Module event aggregation failed', [
                    'user_id' => $this->userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('Calendar sync completed', [
            'user_id'  => $this->userId,
            'provider' => $this->provider ?? 'all',
            'synced'   => $synced,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncCalendarJob permanently failed', [
            'user_id'  => $this->userId,
            'provider' => $this->provider,
            'error'    => $exception->getMessage(),
        ]);
    }
}
