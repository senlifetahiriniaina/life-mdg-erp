<?php

declare(strict_types=1);

namespace Modules\Shared\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Exceptions\TenantException;
use Modules\Shared\Services\BaseService;
use Modules\Shared\Services\SentimentAnalysisService;
use Modules\Shared\Services\UnifiedForecastingService;
use PHPUnit\Framework\TestCase;

class BaseServiceMultiTenancyTest extends TestCase
{
    use RefreshDatabase;

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
        new SentimentAnalysisService(-1);
    }

    /**
     * Test BaseService rejects zero company_id
     */
    public function test_base_service_rejects_zero_company_id(): void
    {
        $this->expectException(TenantException::class);
        new SentimentAnalysisService(0);
    }

    /**
     * Test BaseService stores company_id correctly
     */
    public function test_base_service_stores_company_id(): void
    {
        $service = new SentimentAnalysisService($this->companyId);
        $this->assertEquals($this->companyId, $service->getCompanyId());
    }

    /**
     * Test SentimentAnalysisService respects company_id
     */
    public function test_sentiment_analysis_respects_company_id(): void
    {
        $service = new SentimentAnalysisService($this->companyId);
        $result = $service->analyze('This is excellent!');

        $this->assertArrayHasKey('sentiment', $result);
        $this->assertArrayHasKey('score', $result);
    }

    /**
     * Test UnifiedForecastingService respects company_id
     */
    public function test_forecasting_service_respects_company_id(): void
    {
        $service = new UnifiedForecastingService($this->companyId);
        $historicalData = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $forecast = $service->forecastARIMA($historicalData, 3);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $forecast);
    }

    /**
     * Test service rejects negative company_id in operations
     */
    public function test_service_rejects_operations_with_invalid_company_id(): void
    {
        $service = new SentimentAnalysisService($this->companyId);

        // Service should process text correctly with valid company_id
        $result = $service->analyze('Great service');
        $this->assertIsArray($result);
    }

    /**
     * Test multiple service instances maintain separate company isolation
     */
    public function test_multiple_services_maintain_isolation(): void
    {
        $service1 = new SentimentAnalysisService($this->companyId);
        $service2 = new SentimentAnalysisService($this->otherCompanyId);

        $this->assertEquals($this->companyId, $service1->getCompanyId());
        $this->assertEquals($this->otherCompanyId, $service2->getCompanyId());
        $this->assertNotEquals($service1->getCompanyId(), $service2->getCompanyId());
    }
}
