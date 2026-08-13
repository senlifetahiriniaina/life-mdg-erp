<?php

use Modules\Accounting\Models\GLAccount;

describe('Accounting Reports', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can get financial metrics', function () {
        GLAccount::factory()->create(['account_type' => 'asset']);
        GLAccount::factory()->create(['account_type' => 'liability']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/reports/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_assets',
                'total_liabilities',
                'net_income',
            ]);
    });

    test('can get income statement', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/reports/income-statement?'.http_build_query([
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure(['revenue', 'expenses', 'net_income']);
    });

    test('can get balance sheet', function () {
        GLAccount::factory()->create(['account_type' => 'asset']);
        GLAccount::factory()->create(['account_type' => 'liability']);
        GLAccount::factory()->create(['account_type' => 'equity']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/reports/balance-sheet');

        $response->assertStatus(200)
            ->assertJsonStructure(['assets', 'liabilities', 'equity']);
    });
});
