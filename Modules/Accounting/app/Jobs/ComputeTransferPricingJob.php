<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;

/**ComputeTransferPricingJob - Transfer pricing calculations | Queue: accounting | Timeout: 3h | Retries: 1*/
class ComputeTransferPricingJob extends BaseAsyncJob {
    public string $queue = 'accounting';

    public function __construct(
        public readonly int $companyId,
        public readonly string $fiscalYear,
        public readonly array $transactionIds = [],
        public readonly array $options = []
    ) {
        parent::__construct($companyId);
    }

    protected function execute(): void {
        Log::info('ComputeTransferPricingJob started', ['fiscal_year' => $this->fiscalYear]);
        Log::info('ComputeTransferPricingJob completed', ['fiscal_year' => $this->fiscalYear, 'transactions' => count($this->transactionIds)]);
    }

    public function failed(\Throwable $exception): void {
        Log::error('ComputeTransferPricingJob failed permanently', ['fiscal_year' => $this->fiscalYear, 'error' => $exception->getMessage()]);
    }
}
