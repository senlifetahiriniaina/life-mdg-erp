<?php
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;

class UpdateDeferredTaxJob extends BaseAsyncJob {
    public string $queue = 'accounting';

    public function __construct(
        public readonly int $companyId,
        public readonly string $fiscalYear,
        public readonly float $taxRate = 0.21,
        public readonly array $options = []
    ) {
        parent::__construct($companyId);
    }

    protected function execute(): void {
        Log::info('UpdateDeferredTaxJob started', ['year' => $this->fiscalYear]);
        $differences = 500000;
        $dta = $differences * $this->taxRate;
        $allowance = $differences > 1000000 ? $dta * 0.25 : 0;
        Log::info('UpdateDeferredTaxJob completed', ['net' => round($dta - $allowance, 2)]);
    }
}
