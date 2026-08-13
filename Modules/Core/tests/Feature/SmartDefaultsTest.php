<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Services\SmartDefaultsService;
use Tests\TestCase;

/**
 * SmartDefaultsTest — Simplicity First
 *
 * Verifies that SmartDefaultsService returns correct country defaults
 * (tax rates, currencies, payment methods, fiscal year start) and
 * industry unit defaults for all supported markets.
 */
class SmartDefaultsTest extends TestCase
{
    private SmartDefaultsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SmartDefaultsService();
    }

    // ─── Tax rate tests ───────────────────────────────────────────────────────

    public function test_tax_rate_sn_returns_18(): void
    {
        $this->assertSame(18.0, $this->service->taxRate('SN'));
    }

    public function test_tax_rate_ng_returns_7_5(): void
    {
        $this->assertSame(7.5, $this->service->taxRate('NG'));
    }

    public function test_tax_rate_cm_returns_19_25(): void
    {
        $this->assertSame(19.25, $this->service->taxRate('CM'));
    }

    public function test_tax_rate_ke_returns_16(): void
    {
        $this->assertSame(16.0, $this->service->taxRate('KE'));
    }

    public function test_tax_rate_cn_returns_13(): void
    {
        $this->assertSame(13.0, $this->service->taxRate('CN'));
    }

    public function test_tax_rate_unknown_country_falls_back_to_sn_rate(): void
    {
        // Unknown country → fallback to SN (18%)
        $this->assertSame(18.0, $this->service->taxRate('XX'));
    }

    // ─── Currency tests ───────────────────────────────────────────────────────

    public function test_currency_sn_returns_xof(): void
    {
        $this->assertSame('XOF', $this->service->currency('SN'));
    }

    public function test_currency_ke_returns_kes(): void
    {
        $this->assertSame('KES', $this->service->currency('KE'));
    }

    public function test_currency_in_returns_inr(): void
    {
        $this->assertSame('INR', $this->service->currency('IN'));
    }

    public function test_currency_cn_returns_cny(): void
    {
        $this->assertSame('CNY', $this->service->currency('CN'));
    }

    public function test_currency_ng_returns_ngn(): void
    {
        $this->assertSame('NGN', $this->service->currency('NG'));
    }

    // ─── Fiscal year start tests ──────────────────────────────────────────────

    public function test_fiscal_year_start_ke_returns_7(): void
    {
        $this->assertSame(7, $this->service->fiscalYearStart('KE'));
    }

    public function test_fiscal_year_start_sn_returns_1(): void
    {
        $this->assertSame(1, $this->service->fiscalYearStart('SN'));
    }

    public function test_fiscal_year_start_in_returns_4(): void
    {
        $this->assertSame(4, $this->service->fiscalYearStart('IN'));
    }

    public function test_fiscal_year_start_tz_returns_7(): void
    {
        $this->assertSame(7, $this->service->fiscalYearStart('TZ'));
    }

    // ─── Payment methods tests ────────────────────────────────────────────────

    public function test_payment_methods_sn_includes_orange_money_and_wave(): void
    {
        $methods = $this->service->paymentMethods('SN');
        $this->assertContains('orange_money', $methods);
        $this->assertContains('wave', $methods);
    }

    public function test_payment_methods_ke_includes_m_pesa(): void
    {
        $methods = $this->service->paymentMethods('KE');
        $this->assertContains('m_pesa', $methods);
    }

    public function test_payment_methods_cn_includes_wechat_and_alipay(): void
    {
        $methods = $this->service->paymentMethods('CN');
        $this->assertContains('wechat_pay', $methods);
        $this->assertContains('alipay', $methods);
    }

    public function test_all_supported_countries_have_non_empty_payment_methods(): void
    {
        $countries = $this->service->allCountries();
        $this->assertNotEmpty($countries, 'No countries returned');

        foreach (array_keys($countries) as $code) {
            $methods = $this->service->paymentMethods($code);
            $this->assertNotEmpty(
                $methods,
                "Country {$code} has no payment methods"
            );
        }
    }

    // ─── Units tests ──────────────────────────────────────────────────────────

    public function test_units_textile_includes_metre(): void
    {
        $this->assertContains('mètre', $this->service->units('textile'));
    }

    public function test_units_unknown_industry_returns_general_defaults(): void
    {
        $units = $this->service->units('unknown_xyz');
        $this->assertNotEmpty($units);
        $this->assertContains('unité', $units);
    }

    // ─── OHADA class tests ────────────────────────────────────────────────────

    public function test_ohada_class_asset_returns_2(): void
    {
        $this->assertSame(2, $this->service->ohadaClass('asset'));
    }

    public function test_ohada_class_liability_returns_1(): void
    {
        $this->assertSame(1, $this->service->ohadaClass('liability'));
    }

    public function test_ohada_class_revenue_returns_7(): void
    {
        $this->assertSame(7, $this->service->ohadaClass('revenue'));
    }

    public function test_ohada_class_expense_returns_6(): void
    {
        $this->assertSame(6, $this->service->ohadaClass('expense'));
    }

    public function test_ohada_class_cash_returns_5(): void
    {
        $this->assertSame(5, $this->service->ohadaClass('cash'));
    }

    public function test_ohada_class_unknown_type_returns_4(): void
    {
        $this->assertSame(4, $this->service->ohadaClass('unknown_xyz'));
    }

    // ─── getDefaults integration test ────────────────────────────────────────

    public function test_get_defaults_returns_required_keys(): void
    {
        $defaults = $this->service->getDefaults('sales', 'SN', 'textile');

        $this->assertArrayHasKey('currency', $defaults);
        $this->assertArrayHasKey('taxRate', $defaults);
        $this->assertArrayHasKey('units', $defaults);
        $this->assertArrayHasKey('paymentMethods', $defaults);
        $this->assertArrayHasKey('fiscalYearStart', $defaults);
        $this->assertArrayHasKey('accountingStd', $defaults);
    }

    public function test_get_defaults_sn_textile_has_correct_values(): void
    {
        $defaults = $this->service->getDefaults('sales', 'SN', 'textile');

        $this->assertSame('XOF', $defaults['currency']);
        $this->assertSame(18.0, $defaults['taxRate']);
        $this->assertContains('mètre', $defaults['units']);
        $this->assertContains('orange_money', $defaults['paymentMethods']);
    }

    public function test_get_defaults_unknown_country_uses_fallback(): void
    {
        $defaults = $this->service->getDefaults('sales', 'XX', 'general');

        // Fallback is SN
        $this->assertSame('XOF', $defaults['currency']);
        $this->assertSame(18.0, $defaults['taxRate']);
        $this->assertNotEmpty($defaults['paymentMethods']);
    }
}
