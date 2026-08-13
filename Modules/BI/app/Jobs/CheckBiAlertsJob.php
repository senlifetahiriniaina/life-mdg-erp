<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\BI\Models\BiAlert;
use Modules\BI\Services\AlertService;

class CheckBiAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct() {}

    public function handle(AlertService $alertService): void
    {
        $alerts = BiAlert::where('status', 'active')
            ->with(['biQuery', 'widget'])
            ->get();

        foreach ($alerts as $alert) {
            try {
                $alertService->checkAlert($alert);
            } catch (\Throwable $e) {
                Log::error('BI alert check job failed', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
