<?php

declare(strict_types=1);

namespace Modules\Shared\Tests\Feature;

use Modules\Shared\Exceptions\TenantException;
use Modules\Shared\Services\BaseService;
use Tests\TestCase;

/**
 * Chantier 32.8: this file exclusively used SentimentAnalysisService/
 * UnifiedForecastingService (both deleted — confirmed fully dead, zero
 * real callers anywhere, each a functional duplicate of a real, live
 * implementation elsewhere — see SharedServicesTest.php's docblock) purely
 * as vehicles to exercise BaseService's own tenant-isolation contract, not
 * to test anything unique to either service. Rewired onto a small local
 * concrete BaseService subclass so this real BaseService coverage (still
 * genuinely load-bearing — 13 real services across Accounting/CRM/
 * Helpdesk/BI extend it, 3 of them reachable from real routed controllers)
 * survives the deletion without depending on dead code.
 */
class TenancyTestableService extends BaseService
{
}

class BaseServiceMultiTenancyTest extends TestCase
{
    protected int $companyId;
    protected int $otherCompanyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyId = 1;
        $this->otherCompanyId = 2;
    }

    /**
     * Test BaseService enforces company_id in constructor
     */
    public function test_base_service_validates_company_id(): void
    {
        $this->expectException(TenantException::class);
        new TenancyTestableService(-1);
    }

    /**
     * Test BaseService rejects zero company_id
     */
    public function test_base_service_rejects_zero_company_id(): void
    {
        $this->expectException(TenantException::class);
        new TenancyTestableService(0);
    }

    /**
     * Test BaseService stores company_id correctly
     */
    public function test_base_service_stores_company_id(): void
    {
        $service = new TenancyTestableService($this->companyId);
        $this->assertEquals($this->companyId, $service->getCompanyId());
    }

    /**
     * Test multiple service instances maintain separate company isolation
     */
    public function test_multiple_services_maintain_isolation(): void
    {
        $service1 = new TenancyTestableService($this->companyId);
        $service2 = new TenancyTestableService($this->otherCompanyId);

        $this->assertEquals($this->companyId, $service1->getCompanyId());
        $this->assertEquals($this->otherCompanyId, $service2->getCompanyId());
        $this->assertNotEquals($service1->getCompanyId(), $service2->getCompanyId());
    }
}
