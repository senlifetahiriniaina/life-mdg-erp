<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 19 Lot 3: empirical re-verification of SuperadminController /
 * TenantManagerService (wired up in Chantier 8.3cs, never actually
 * exercised over the real HTTP route with a real request body since).
 *
 * Found a real, previously-undocumented bug via a real POST request:
 * TenantManagerService::provision() read
 * $defaults['payment_methods']/$defaults['tax_rate'] (snake_case), but
 * TenantManagerService::getSmartDefaults() only ever aliased
 * accounting_std/accounting_standard/locale/timezone from
 * SmartDefaultsService::getDefaults()'s real camelCase-only return shape
 * (taxRate/paymentMethods/...) — a guaranteed "Undefined array key" 500 on
 * *every* real tenant provisioning call, confirmed empirically before the
 * fix. getSmartDefaults() now aliases every key its own docblock already
 * promised (tax_rate/tax_label/payment_methods/fiscal_year_start/
 * mobile_country_code), not just the two call sites provision() happened
 * to use.
 */
class SuperadminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_suspend_reactivate_export_and_audit_log_work_over_http()
    {
        $superAdmin = $this->actingAsUser('super-admin');

        $provision = $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/superadmin/tenants', [
            'name' => 'Regression Tenant',
            'country_code' => 'MG',
            'industry' => 'textile',
            'plan' => 'starter',
            'owner_email' => 'regression-owner@example.com',
            'owner_name' => 'Regression Owner',
        ]);

        $provision->assertCreated();
        $provision->assertJsonPath('tenant.settings.payment_methods', ['mvola', 'orange_money', 'airtel_money', 'cash', 'bank_transfer']);
        $provision->assertJsonPath('tenant.settings.tax_rate', 20);
        $provision->assertJsonPath('tenant.settings.accounting_std', 'PCG');
        $tenantId = $provision->json('tenant.id');
        expect($tenantId)->not->toBeNull();

        $suspend = $this->actingAs($superAdmin, 'sanctum')->postJson("/api/v1/superadmin/tenants/{$tenantId}/suspend", [
            'reason' => 'regression test',
        ]);
        $suspend->assertOk();

        $reactivate = $this->actingAs($superAdmin, 'sanctum')->postJson("/api/v1/superadmin/tenants/{$tenantId}/reactivate");
        $reactivate->assertOk();

        $auditLog = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/superadmin/audit-log');
        $auditLog->assertOk();
        $actions = collect($auditLog->json('data'))->pluck('action');
        expect($actions)->toContain('provision')
            ->toContain('suspend')
            ->toContain('reactivate');

        $export = $this->actingAs($superAdmin, 'sanctum')->getJson("/api/v1/superadmin/tenants/{$tenantId}/export");
        $export->assertOk();
        $export->assertJsonStructure(['message', 'export_url']);
    }

    public function test_get_smart_defaults_returns_the_snake_case_keys_its_docblock_promises()
    {
        $defaults = app(\Modules\Core\Services\TenantManagerService::class)->getSmartDefaults('MG', 'textile');

        expect($defaults)->toHaveKeys(['tax_rate', 'tax_label', 'payment_methods', 'fiscal_year_start', 'mobile_country_code', 'accounting_std']);
        expect($defaults['tax_rate'])->toBe(20.0);
        expect($defaults['payment_methods'])->toBe(['mvola', 'orange_money', 'airtel_money', 'cash', 'bank_transfer']);
    }
}
