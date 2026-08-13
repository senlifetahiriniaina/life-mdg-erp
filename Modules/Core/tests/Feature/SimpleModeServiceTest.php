<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Services\SimpleModeService;
use Tests\TestCase;

/**
 * SimpleModeServiceTest — Simplicity First
 *
 * Verifies that SimpleModeService correctly identifies advanced fields,
 * manages user mode preferences, and filters visible fields accordingly.
 */
class SimpleModeServiceTest extends TestCase
{
    private SimpleModeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SimpleModeService();
        Cache::flush();
    }

    // ─── hiddenFields tests ───────────────────────────────────────────────────

    public function test_hidden_fields_sales_includes_discount_percent(): void
    {
        $this->assertContains('discount_percent', $this->service->hiddenFields('sales'));
    }

    public function test_hidden_fields_sales_includes_opportunity_id(): void
    {
        $this->assertContains('opportunity_id', $this->service->hiddenFields('sales'));
    }

    public function test_hidden_fields_accounting_includes_analytical_axis(): void
    {
        $this->assertContains('analytical_axis', $this->service->hiddenFields('accounting'));
    }

    public function test_hidden_fields_accounting_includes_cost_center(): void
    {
        $this->assertContains('cost_center', $this->service->hiddenFields('accounting'));
    }

    public function test_hidden_fields_hr_includes_contract_type_details(): void
    {
        $this->assertContains('contract_type_details', $this->service->hiddenFields('hr'));
    }

    public function test_hidden_fields_inventory_includes_abc_class(): void
    {
        $this->assertContains('abc_class', $this->service->hiddenFields('inventory'));
    }

    public function test_hidden_fields_unknown_module_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->hiddenFields('unknown_module'));
    }

    // ─── isAdvanced tests ─────────────────────────────────────────────────────

    public function test_is_advanced_sales_discount_percent_returns_true(): void
    {
        $this->assertTrue($this->service->isAdvanced('sales', 'discount_percent'));
    }

    public function test_is_advanced_sales_tax_rate_returns_true(): void
    {
        $this->assertTrue($this->service->isAdvanced('sales', 'tax_rate'));
    }

    public function test_is_advanced_sales_description_returns_false(): void
    {
        $this->assertFalse($this->service->isAdvanced('sales', 'description'));
    }

    public function test_is_advanced_sales_customer_name_returns_false(): void
    {
        $this->assertFalse($this->service->isAdvanced('sales', 'customer_name'));
    }

    public function test_is_advanced_accounting_analytical_axis_returns_true(): void
    {
        $this->assertTrue($this->service->isAdvanced('accounting', 'analytical_axis'));
    }

    public function test_is_advanced_hr_secondary_bank_returns_true(): void
    {
        $this->assertTrue($this->service->isAdvanced('hr', 'secondary_bank'));
    }

    // ─── User mode persistence tests ──────────────────────────────────────────

    public function test_is_simple_mode_defaults_to_true_for_new_user(): void
    {
        $this->assertTrue($this->service->isSimpleMode(999));
    }

    public function test_set_mode_to_expert_persists_correctly(): void
    {
        $this->service->setMode(1, false);
        $this->assertFalse($this->service->isSimpleMode(1));
    }

    public function test_set_mode_to_simple_persists_correctly(): void
    {
        $this->service->setMode(1, false);
        $this->service->setMode(1, true);
        $this->assertTrue($this->service->isSimpleMode(1));
    }

    // ─── visibleFields tests ──────────────────────────────────────────────────

    public function test_visible_fields_simple_mode_excludes_advanced_fields(): void
    {
        $allFields = ['customer_name', 'amount', 'discount_percent', 'tax_rate', 'description'];
        $visible = $this->service->visibleFields('sales', $allFields, true);

        $this->assertNotContains('discount_percent', $visible);
        $this->assertNotContains('tax_rate', $visible);
        $this->assertContains('customer_name', $visible);
        $this->assertContains('amount', $visible);
        $this->assertContains('description', $visible);
    }

    public function test_visible_fields_expert_mode_returns_all_fields(): void
    {
        $allFields = ['customer_name', 'amount', 'discount_percent', 'tax_rate', 'description'];
        $visible = $this->service->visibleFields('sales', $allFields, false);

        $this->assertSame($allFields, $visible);
    }

    public function test_skipped_steps_sales_in_simple_mode(): void
    {
        $steps = $this->service->skippedSteps('sales');
        $this->assertNotEmpty($steps);
        $this->assertContains('advanced_pricing', $steps);
    }

    public function test_skipped_steps_unknown_module_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->skippedSteps('unknown_module'));
    }
}
