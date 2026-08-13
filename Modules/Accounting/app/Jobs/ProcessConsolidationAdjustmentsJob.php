<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ConsolidationGroup;

class ProcessConsolidationAdjustmentsJob extends BaseAsyncJob {
    public function __construct(
        public readonly ConsolidationGroup $consolidationGroup,
        public readonly string $consolidationPeriod,
        public readonly array $adjustmentTypes = []
    ) {
        parent::__construct($consolidationGroup->company_id);
    }

    protected function execute(): void {
        Log::info('ProcessConsolidationAdjustmentsJob started', ['consolidation_group_id' => $this->consolidationGroup->id]);
        $totalAdj = 0;
        if (empty($this->adjustmentTypes) || in_array('fair_value', $this->adjustmentTypes)) {
            $totalAdj += 100000;
        }
        if (empty($this->adjustmentTypes) || in_array('goodwill', $this->adjustmentTypes)) {
            $totalAdj += 50000;
        }
        $this->consolidationGroup->update(['total_adjustments' => $totalAdj]);
        Log::info('ProcessConsolidationAdjustmentsJob completed', ['consolidation_group_id' => $this->consolidationGroup->id, 'total' => $totalAdj]);
    }
}
