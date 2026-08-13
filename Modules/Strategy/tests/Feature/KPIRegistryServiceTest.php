<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Services\KPIRegistryService;

class KPIRegistryServiceTest extends TestCase
{
    private KPIRegistryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KPIRegistryService::class);
    }

    /** @test */
    public function it_returns_all_kpis_by_module()
    {
        $kpis = $this->service->all();

        $this->assertIsArray($kpis);
        $this->assertNotEmpty($kpis);
        $this->assertArrayHasKey('Accounting', $kpis);
        $this->assertArrayHasKey('CRM', $kpis);
        $this->assertArrayHasKey('HR', $kpis);
        $this->assertArrayHasKey('Inventory', $kpis);
        $this->assertArrayHasKey('Manufacturing', $kpis);
    }

    /** @test */
    public function it_returns_kpis_for_specific_module()
    {
        $accountingKpis = $this->service->forModule('Accounting');

        $this->assertIsArray($accountingKpis);
        $this->assertNotEmpty($accountingKpis);
        $this->assertArrayHasKey('current_ratio', $accountingKpis);
        $this->assertArrayHasKey('debt_to_equity', $accountingKpis);
        $this->assertArrayHasKey('net_profit_margin', $accountingKpis);
    }

    /** @test */
    public function it_returns_empty_array_for_unknown_module()
    {
        $unknownKpis = $this->service->forModule('NonExistentModule');
        $this->assertIsArray($unknownKpis);
        $this->assertEmpty($unknownKpis);
    }

    /** @test */
    public function kpi_has_required_structure()
    {
        $kpis = $this->service->forModule('Accounting');
        $currentRatio = $kpis['current_ratio'];

        $this->assertArrayHasKey('label', $currentRatio);
        $this->assertArrayHasKey('unit', $currentRatio);
        $this->assertArrayHasKey('direction', $currentRatio);
        $this->assertArrayHasKey('formula', $currentRatio);
        $this->assertArrayHasKey('value_callback', $currentRatio);

        $this->assertIsString($currentRatio['label']);
        $this->assertIsString($currentRatio['unit']);
        $this->assertIn($currentRatio['direction'], ['up', 'down', 'target']);
        $this->assertIsString($currentRatio['formula']);
        $this->assertIsCallable($currentRatio['value_callback']);
    }

    /** @test */
    public function it_returns_value_for_existing_kpi()
    {
        $value = $this->service->getValue('Accounting', 'current_ratio');

        $this->assertIsFloat($value);
        $this->assertGreaterThanOrEqual(0, $value);
    }

    /** @test */
    public function it_returns_zero_for_non_existing_kpi()
    {
        $value = $this->service->getValue('Accounting', 'non_existing_kpi');

        $this->assertIsFloat($value);
        $this->assertEquals(0.0, $value);
    }

    /** @test */
    public function it_returns_zero_for_non_existing_module()
    {
        $value = $this->service->getValue('NonExistent', 'any_kpi');

        $this->assertIsFloat($value);
        $this->assertEquals(0.0, $value);
    }

    /** @test */
    public function crm_module_has_required_kpis()
    {
        $crmKpis = $this->service->forModule('CRM');

        $expectedKpis = ['lead_conversion_rate', 'customer_acquisition_cost', 'customer_lifetime_value'];
        foreach ($expectedKpis as $kpi) {
            $this->assertArrayHasKey($kpi, $crmKpis, "CRM should have {$kpi} KPI");
        }
    }

    /** @test */
    public function hr_module_has_required_kpis()
    {
        $hrKpis = $this->service->forModule('HR');

        $expectedKpis = ['employee_turnover_rate', 'revenue_per_employee'];
        foreach ($expectedKpis as $kpi) {
            $this->assertArrayHasKey($kpi, $hrKpis, "HR should have {$kpi} KPI");
        }
    }

    /** @test */
    public function inventory_module_has_required_kpis()
    {
        $inventoryKpis = $this->service->forModule('Inventory');

        $expectedKpis = ['inventory_turnover', 'stockout_rate', 'fill_rate'];
        foreach ($expectedKpis as $kpi) {
            $this->assertArrayHasKey($kpi, $inventoryKpis, "Inventory should have {$kpi} KPI");
        }
    }

    /** @test */
    public function kpi_value_callbacks_are_callable()
    {
        $allKpis = $this->service->all();

        foreach ($allKpis as $module => $kpis) {
            foreach ($kpis as $key => $kpiDef) {
                $this->assertIsCallable($kpiDef['value_callback'],
                    "{$module}.{$key} value_callback should be callable");
            }
        }
    }
}
