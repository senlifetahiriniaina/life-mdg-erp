<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ConsolidationGroup;

class CalculateMinorityInterestJob extends BaseAsyncJob {
    public function __construct(
        public readonly ConsolidationGroup $consolidationGroup,
        public readonly string $consolidationPeriod
    ) {
        parent::__construct($consolidationGroup->company_id);
    }

    protected function execute(): void {
        Log::info('CalculateMinorityInterestJob started', ['consolidation_group_id' => $this->consolidationGroup->id]);
        $totalMinority = 0;
        foreach ($this->consolidationGroup->members()->where('ownership_percentage', '<', 100)->get() as $m) {
            $totalMinority += (100 - $m->ownership_percentage) * 100;
        }
        $this->consolidationGroup->update(['total_minority_interest_equity' => $totalMinority]);
        Log::info('CalculateMinorityInterestJob completed', ['consolidation_group_id' => $this->consolidationGroup->id, 'total' => $totalMinority]);
    }
}
