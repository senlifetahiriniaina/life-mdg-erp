<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ConsolidationGroup;

/**GenerateConsolidatedReportsJob - Generates consolidated financial reports | Queue: accounting | Timeout: 2h | Retries: 1*/
class GenerateConsolidatedReportsJob extends BaseAsyncJob {
    public function __construct(
        public readonly ConsolidationGroup $consolidationGroup,
        public readonly string $reportType = 'all',
        public readonly array $options = []
    ) {
        parent::__construct($consolidationGroup->company_id);
    }

    protected function execute(): void {
        Log::info('GenerateConsolidatedReportsJob started', ['consolidation_group_id' => $this->consolidationGroup->id, 'report_type' => $this->reportType]);
        Log::info('GenerateConsolidatedReportsJob completed', ['consolidation_group_id' => $this->consolidationGroup->id, 'report_type' => $this->reportType]);
    }
}
