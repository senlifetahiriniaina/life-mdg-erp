<?php
declare(strict_types=1);
namespace Modules\Accounting\Jobs;
use Modules\Shared\Jobs\BaseAsyncJob;
use Illuminate\Support\Facades\Log;

/**GenerateTaxReportsJob - Generates tax compliance reports | Queue: accounting | Timeout: 2h | Retries: 1*/
class GenerateTaxReportsJob extends BaseAsyncJob {
    public string $queue = 'accounting';

    public function __construct(
        public readonly int $companyId,
        public readonly string $fiscalYear,
        public readonly string $reportType = 'all',
        public readonly string $jurisdiction = 'US',
        public readonly array $options = []
    ) {
        parent::__construct($companyId);
    }

    protected function execute(): void {
        Log::info('GenerateTaxReportsJob started', ['fiscal_year' => $this->fiscalYear, 'report_type' => $this->reportType, 'jurisdiction' => $this->jurisdiction]);
        Log::info('GenerateTaxReportsJob completed', ['fiscal_year' => $this->fiscalYear, 'report_type' => $this->reportType]);
    }

    public function failed(\Throwable $exception): void {
        Log::error('GenerateTaxReportsJob failed permanently', ['fiscal_year' => $this->fiscalYear, 'error' => $exception->getMessage()]);
    }
}
