<?php
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;

class CalculateTaxProvisionsJob extends BaseAsyncJob {
    public string $queue = 'accounting';

    public function __construct(
        public readonly int $companyId,
        public readonly string $fiscalYear,
        public readonly array $options = []
    ) {
        parent::__construct($companyId);
    }

    protected function execute(): void {
        Log::info('CalculateTaxProvisionsJob started', ['year' => $this->fiscalYear]);
        $taxable = 1000000;
        $provision = $taxable * 0.21 + ($taxable * 0.21 * 0.10);
        Log::info('CalculateTaxProvisionsJob completed', ['provision' => round($provision, 2)]);
    }
}
