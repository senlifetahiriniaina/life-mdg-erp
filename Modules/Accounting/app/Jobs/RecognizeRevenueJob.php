<?php

declare(strict_types=1);

namespace Modules\Accounting\Jobs;

use Modules\Shared\Jobs\BaseAsyncJob;
use Modules\Accounting\Models\RevenueRecognitionSchedule;
use Modules\Accounting\Services\ASC606RevenueRecognitionService;

class RecognizeRevenueJob extends BaseAsyncJob
{
    public function __construct(private RevenueRecognitionSchedule $schedule)
    {
        parent::__construct($schedule->company_id);
    }

    protected function execute(): void
    {
        $service = app(ASC606RevenueRecognitionService::class);
        $service->recognizeRevenue($this->schedule);
        \Log::info("Revenue recognized for schedule {$this->schedule->id}");
    }
}
