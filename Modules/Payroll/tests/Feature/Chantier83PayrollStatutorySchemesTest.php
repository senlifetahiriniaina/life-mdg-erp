<?php

declare(strict_types=1);

use Modules\Payroll\Data\StatutorySchemes;
use Modules\Payroll\Services\PayrollIntegrationService;

/**
 * Chantier 8.3 (Payroll): StatutorySchemes — 8 real African statutory tax
 * schedules (SN/CI/CM/MG/BJ/TG/BF/ML) — existed fully written with zero
 * consumers anywhere in this app. calculateIncomeTax()/calculateSocialSecurity()/
 * calculatePensionContribution() now read from it for supported countries,
 * with the old crude flat-rate approximations kept only as an explicit
 * fallback for countries the real dataset doesn't cover.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('Madagascar income tax enforces the real IRSA minimum de perception', function () {
    $service = app(PayrollIntegrationService::class);

    // Gross low enough that the bracket tax would be 0, but IRSA has a
    // real statutory minimum_tax floor of 3,000 MGA.
    expect($service->calculateIncomeTax(340_000, 'MG'))->toBe(3_000.0);
});

test('Burkina Faso income tax applies all 7 real progressive brackets', function () {
    $service = app(PayrollIntegrationService::class);

    // No abatement for BF — taxableBase === gross. Spans every bracket.
    $tax = $service->calculateIncomeTax(300_000, 'BF');

    expect($tax)->toBeGreaterThan(0.0);
    // Independently reconstruct the same real brackets and compare.
    $brackets = StatutorySchemes::country('BF')['income_tax']['brackets'];
    $expected = 0.0;
    foreach ($brackets as [$lower, $upper, $rate]) {
        if (300_000 <= $lower) {
            break;
        }
        $top = $upper === null ? 300_000 : min(300_000, $upper);
        $expected += max(0, $top - $lower) * $rate;
    }
    expect($tax)->toBe(round($expected, 2));
});

test('a country not in StatutorySchemes falls back to the old approximation without error', function () {
    $service = app(PayrollIntegrationService::class);

    expect(StatutorySchemes::country('ZZ'))->toBeNull();
    expect($service->calculateIncomeTax(500_000, 'ZZ'))->toBe(500_000 * 0.15);
    expect($service->calculateSocialSecurity(500_000, 'ZZ'))->toBe(500_000 * 0.06);
    expect($service->calculatePensionContribution(500_000, 'ZZ'))->toBe(500_000 * 0.05);
});

test('social security and pension contributions never double-count a combined scheme', function () {
    $service = app(PayrollIntegrationService::class);

    // BJ has a single combined cnss scheme (no pension/social split).
    $ss = $service->calculateSocialSecurity(600_000, 'BJ');
    $pension = $service->calculatePensionContribution(600_000, 'BJ');

    expect($pension)->toBe(0.0);
    expect($ss)->toBe(round(600_000 * 0.036, 2));
});

test('scheme ceilings cap the contribution base', function () {
    $service = app(PayrollIntegrationService::class);

    // SN ipres (pension) ceiling is 432,000 — gross well above it.
    $pension = $service->calculatePensionContribution(2_000_000, 'SN');
    expect($pension)->toBe(round(432_000 * 0.056, 2));
});
