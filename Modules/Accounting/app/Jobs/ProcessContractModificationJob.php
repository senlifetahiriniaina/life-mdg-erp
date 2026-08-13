<?php
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Models\RevenueContract;

class ProcessContractModificationJob extends BaseAsyncJob {
    public string $queue = 'accounting';

    public function __construct(
        public readonly RevenueContract $contract,
        public readonly array $modificationData
    ) {
        parent::__construct($contract->company_id);
    }

    protected function execute(): void {
        Log::info('ProcessContractModificationJob started', ['contract' => $this->contract->id]);
        $priceChange = $this->modificationData['price_change'] ?? 0;
        $this->contract->update([
            'contract_value' => max(0, ($this->contract->contract_value ?? 0) + $priceChange),
            'has_contract_modification' => true
        ]);
        Log::info('ProcessContractModificationJob completed', ['adjustment' => $priceChange]);
    }
}
