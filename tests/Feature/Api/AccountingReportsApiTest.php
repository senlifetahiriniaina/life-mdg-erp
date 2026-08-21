<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;

uses(RefreshDatabase::class);

// ── Auth guard ────────────────────────────────────────────────────────────────

test('balance sheet requires authentication', function () {
    $this->getJson('/api/v1/accounting/reports/balance-sheet')->assertUnauthorized();
});

test('income statement requires authentication', function () {
    $this->getJson('/api/v1/accounting/reports/income-statement')->assertUnauthorized();
});

test('cash flow requires authentication', function () {
    $this->getJson('/api/v1/accounting/reports/cash-flow')->assertUnauthorized();
});

test('ledger matching index requires authentication', function () {
    $this->getJson('/api/v1/accounting/matching')->assertUnauthorized();
});

// ── Balance Sheet ─────────────────────────────────────────────────────────────

test('balance sheet returns expected structure', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/balance-sheet')
        ->assertOk()
        ->assertJsonStructure(['as_of', 'compare', 'assets', 'liabilities', 'equity']);
});

test('balance sheet accepts as_of parameter', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/balance-sheet?as_of=2026-12-31')
        ->assertOk()
        ->assertJsonPath('as_of', '2026-12-31');
});

test('balance sheet rejects invalid date', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/balance-sheet?as_of=not-a-date')
        ->assertUnprocessable();
});

test('balance sheet assets section has items and total', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/balance-sheet')
        ->assertOk()
        ->assertJsonStructure([
            'assets' => ['items', 'total', 'previous_total'],
        ]);
});

test('balance sheet includes compare column when compare_as_of provided', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/balance-sheet?as_of=2026-12-31&compare_as_of=2025-12-31')
        ->assertOk()
        ->assertJsonPath('compare', '2025-12-31');
});

// ── Income Statement ──────────────────────────────────────────────────────────

test('income statement returns expected structure', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/income-statement')
        ->assertOk()
        ->assertJsonStructure(['from', 'to', 'revenue', 'expenses', 'net_income']);
});

test('income statement accepts period parameters', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-01-01&to=2026-06-30')
        ->assertOk()
        ->assertJsonPath('from', '2026-01-01')
        ->assertJsonPath('to', '2026-06-30');
});

test('income statement rejects to before from', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-12-31&to=2026-01-01')
        ->assertUnprocessable();
});

test('income statement net income equals revenue minus expenses', function () {
    actingAsUser('accountant');

    $response = $this->getJson('/api/v1/accounting/reports/income-statement')
        ->assertOk()
        ->json();

    $expectedNet = ($response['revenue']['total'] ?? 0) - ($response['expenses']['total'] ?? 0);
    expect(round($response['net_income'], 2))->toBe(round($expectedNet, 2));
});

test('income statement includes variance when compare period provided', function () {
    actingAsUser('accountant');

    $response = $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-01-01&to=2026-12-31&compare_from=2025-01-01&compare_to=2025-12-31')
        ->assertOk()
        ->json();

    expect($response)->toHaveKey('variance');
});

// ── Cash Flow ─────────────────────────────────────────────────────────────────

test('cash flow returns expected structure', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/cash-flow')
        ->assertOk()
        ->assertJsonStructure(['from', 'to', 'operating', 'investing', 'financing', 'net_change']);
});

test('cash flow operating section has total', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/reports/cash-flow')
        ->assertOk()
        ->assertJsonStructure(['operating' => ['net_income', 'total']]);
});

// ── Ledger Matching ───────────────────────────────────────────────────────────

test('ledger matching index returns array', function () {
    actingAsUser('accountant');

    // ReportController::ledgerMatching() has always returned a bare JSON array
    // (response()->json($lines)) since Chantier 19 Lot 1 — confirmed by reading
    // the real controller and the real frontend consumer
    // (resources/js/Pages/Accounting/Lettrage.vue's `lines.value = data`),
    // never a {data, total} envelope. This assertion never matched real
    // behavior; fixed to assert the real shape instead of the controller.
    $response = $this->getJson('/api/v1/accounting/matching')->assertOk();
    expect($response->json())->toBeArray();
});

test('ledger matching match requires at least two line_ids', function () {
    actingAsUser('accountant');

    $this->postJson('/api/v1/accounting/matching/match', ['line_ids' => [1]])
        ->assertUnprocessable();
});

test('ledger unmatch requires match_ref', function () {
    actingAsUser('accountant');

    $this->postJson('/api/v1/accounting/matching/unmatch', [])
        ->assertUnprocessable();
});
