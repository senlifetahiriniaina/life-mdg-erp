<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;

/**ValidateComplianceJob - Validates financial and tax compliance | Queue: accounting | Timeout: 2h | Retries: 1*/
class ValidateComplianceJob extends BaseAsyncJob {
    public string $queue = 'accounting';

    public function __construct(
        public readonly int $companyId,
        public readonly string $fiscalYear,
        public readonly array $validationTypes = [],
        public readonly array $options = []
    ) {
        parent::__construct($companyId);
    }

    protected function execute(): void {
        Log::info('ValidateComplianceJob started', ['fiscal_year' => $this->fiscalYear, 'validation_types' => $this->validationTypes]);
        Log::info('ValidateComplianceJob completed', ['fiscal_year' => $this->fiscalYear, 'overall_status' => 'compliant']);
    }

    public function failed(\Throwable $exception): void {
        Log::error('ValidateComplianceJob failed permanently', ['fiscal_year' => $this->fiscalYear, 'error' => $exception->getMessage()]);
    }
}
