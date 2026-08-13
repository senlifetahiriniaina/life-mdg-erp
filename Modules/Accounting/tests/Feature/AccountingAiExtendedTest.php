<?php

declare(strict_types=1);
use Modules\Accounting\Services\AI\AccountingAIService;


test('can forecast cash flow', function () {
    $mock = Mockery::mock(AccountingAIService::class);
    $mock->shouldReceive('forecastCashFlow')
        ->once()
        ->andReturn(['forecast' => 'projected data', 'forecast_days' => 90]);
    app()->instance(AccountingAIService::class, $mock);

    actingAsUser('accountant');
    $this->postJson('/api/v1/accounting/ai/forecast-cash-flow', [
        'historical_data' => [
            ['date' => '2026-01-01', 'inflow' => 10000, 'outflow' => 8000],
            ['date' => '2026-02-01', 'inflow' => 12000, 'outflow' => 9000],
        ],
        'forecast_days' => 90,
    ])->assertOk()->assertJsonStructure(['forecast', 'forecast_days']);
});

test('can summarize balance sheet', function () {
    $mock = Mockery::mock(AccountingAIService::class);
    $mock->shouldReceive('summarizeBalanceSheet')
        ->once()
        ->andReturn(['narrative' => 'balance sheet summary', 'locale' => 'fr']);
    app()->instance(AccountingAIService::class, $mock);

    actingAsUser('accountant');
    $this->postJson('/api/v1/accounting/ai/summarize-balance-sheet', [
        'balance_sheet_data' => ['total_assets' => 500000, 'total_liabilities' => 200000, 'equity' => 300000],
        'locale' => 'fr',
    ])->assertOk()->assertJsonStructure(['narrative', 'locale']);
});

test('can categorize transactions', function () {
    $mock = Mockery::mock(AccountingAIService::class);
    $mock->shouldReceive('categorizeTransactions')
        ->once()
        ->andReturn(['categorization' => 'categorized data', 'total' => 2]);
    app()->instance(AccountingAIService::class, $mock);

    actingAsUser('accountant');
    $this->postJson('/api/v1/accounting/ai/categorize-transactions', [
        'transactions' => [
            ['id' => 1, 'description' => 'Achat fournitures bureau', 'amount' => 150],
            ['id' => 2, 'description' => 'Loyer mensuel',           'amount' => 2000],
        ],
    ])->assertOk()->assertJsonStructure(['categorization', 'total']);
});
