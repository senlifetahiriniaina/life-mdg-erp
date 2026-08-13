<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\ConsolidationGroup;
use Modules\Accounting\Models\IntercompanyTransaction;

class EliminateIntercompanyJob extends BaseAsyncJob {
    public function __construct(
        public readonly ConsolidationGroup $consolidationGroup,
        public readonly string $consolidationPeriod
    ) {
        parent::__construct($consolidationGroup->company_id);
    }

    protected function execute(): void {
        Log::info('EliminateIntercompanyJob started', ['consolidation_group_id' => $this->consolidationGroup->id]);
        $transactions = IntercompanyTransaction::where('consolidation_group_id', $this->consolidationGroup->id)->where('is_eliminated', false)->get();
        foreach ($transactions as $t) {
            $t->update(['is_eliminated' => true]);
        }
        Log::info('EliminateIntercompanyJob completed', ['consolidation_group_id' => $this->consolidationGroup->id, 'count' => $transactions->count()]);
    }
}
