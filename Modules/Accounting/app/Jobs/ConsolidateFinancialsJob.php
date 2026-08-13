<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ConsolidationGroup;
use Modules\Accounting\Models\ConsolidationReport;
use Modules\Accounting\Services\ConsolidationService;

class ConsolidateFinancialsJob extends BaseAsyncJob {
    public function __construct(
        public readonly ConsolidationGroup $consolidationGroup,
        public readonly string $consolidationPeriod,
        public readonly array $options = []
    ) {
        parent::__construct($consolidationGroup->company_id);
    }

    protected function execute(): void {
        Log::info('ConsolidateFinancialsJob started', ['consolidation_group_id' => $this->consolidationGroup->id, 'consolidation_period' => $this->consolidationPeriod]);
        $this->consolidationGroup->update(['consolidation_status' => 'processing']);
        $consolidatedData = ['total_assets' => 0, 'total_liabilities' => 0, 'total_equity' => 0];
        $report = ConsolidationReport::create(['consolidation_group_id' => $this->consolidationGroup->id, 'report_type' => 'full_consolidation', 'consolidated_data' => $consolidatedData, 'status' => 'draft', 'created_by' => auth()->id() ?? 1]);
        $this->consolidationGroup->update(['consolidation_status' => 'completed', 'last_consolidation_date' => now()]);
        Log::info('ConsolidateFinancialsJob completed', ['consolidation_group_id' => $this->consolidationGroup->id, 'report_id' => $report->id]);
    }
}
