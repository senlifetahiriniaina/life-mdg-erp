<?php

use Carbon\Carbon;
use Modules\Accounting\Models\AssetDepreciation;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Services\FixedAssetService;

// NOTE: This file used to target a parallel, never-wired depreciation stack
// (Modules\Accounting\Models\DepreciationSchedule / DepreciationEntry and the
// /api/accounting/depreciation-schedules routes served by
// DepreciationScheduleController). That controller is not registered in
// Modules/Accounting/routes/api.php (confirmed by grep — zero route
// references) and DepreciationSchedule/DepreciationEntry have no callers
// outside their own tests/factories/policies. The real, live, routed OHADA
// amortissement implementation is FixedAssetController + FixedAssetService,
// backed by FixedAsset + AssetDepreciation and exercised end-to-end by
// FixedAssetTest.php. This file has been rewritten to hit that real API
// instead, keeping the original testing goal (depreciation schedules work
// correctly) but against the real code path.
describe('Depreciation Schedule (via Fixed Assets)', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(FixedAssetService::class);

        $this->assetAccount = ChartOfAccount::factory()->create(['name' => 'Fixed Assets']);
        $this->expenseAccount = ChartOfAccount::factory()->create(['name' => 'Depreciation Expense']);
        $this->accumulatedAccount = ChartOfAccount::factory()->create(['name' => 'Accumulated Depreciation']);
    });

    // Original: test_can_create_depreciation_schedule (POST /api/accounting/depreciation-schedules
    // against the dead DepreciationSchedule model). Real equivalent: creating a fixed asset via
    // the live API IS creating its depreciation schedule — method, useful life, and residual
    // (salvage) value all live on FixedAsset itself; there is no separate persisted "schedule" row.
    test('creating a fixed asset establishes its depreciation schedule parameters', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/fixed-assets', [
                'name' => 'Delivery Van',
                'asset_class' => 'vehicle',
                'acquisition_date' => '2026-01-01',
                'acquisition_cost' => 100000,
                'salvage_value' => 5000,
                'useful_life_years' => 10,
                'depreciation_method' => 'straight-line',
                'asset_account_id' => $this->assetAccount->id,
                'depreciation_expense_account_id' => $this->expenseAccount->id,
                'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
            ]);

        $response->assertCreated();
        expect($response->json('depreciation_method'))->toBe('straight-line');
        expect($response->json('status'))->toBe('active');
        $this->assertDatabaseHas('acc_fixed_assets', [
            'name' => 'Delivery Van',
            'depreciation_method' => 'straight-line',
            'status' => 'active',
        ]);
    });

    // Original: test_can_list_depreciation_schedules (GET list of DepreciationSchedule rows,
    // asserting count 3). Real equivalent: the full multi-period schedule for one asset is
    // returned by GET /fixed-assets/{id}/schedule; asserting a specific period count is the
    // closest live analogue to "list depreciation schedule rows".
    test('can retrieve the full depreciation schedule for an asset', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'List Test Asset',
            'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 36000,
            'salvage_value' => 0,
            'useful_life_years' => 36,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->expenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/fixed-assets/{$asset->id}/schedule");

        $response->assertOk();
        expect($response->json('schedule'))->toHaveCount(36);
        expect($response->json('total_periods'))->toBe(36);
    });

    // Original: test_can_view_depreciation_schedule (GET single DepreciationSchedule by id).
    // Real equivalent: GET /fixed-assets/{id}/schedule returns the asset's depreciation
    // parameters alongside its schedule.
    test('can view a fixed asset depreciation schedule with its method', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'View Test Asset',
            'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 12000,
            'salvage_value' => 0,
            'useful_life_years' => 12,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->expenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/fixed-assets/{$asset->id}/schedule");

        $response->assertOk();
        expect($response->json('depreciation_method'))->toBe($asset->depreciation_method);
        expect($response->json('asset_id'))->toBe($asset->id);
    });

    // Original: test_can_record_depreciation_entry (POST .../record against DepreciationSchedule,
    // asserting a depreciation_entries row with status 'recorded'). Real equivalent:
    // POST /fixed-assets/{id}/depreciate records one period into acc_asset_depreciation
    // via AssetDepreciation. There is no "status" column on the real depreciation-entry
    // table (recording happens by row existing, not a status flag), so that specific
    // assertion is dropped rather than invented.
    test('can record a depreciation entry for a fixed asset', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Record Test Asset',
            'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 12000,
            'salvage_value' => 0,
            'useful_life_years' => 12,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->expenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/fixed-assets/{$asset->id}/depreciate", [
                'period' => '2026-02-01',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('acc_asset_depreciation', [
            'asset_id' => $asset->id,
            'period_month' => 2,
            'period_year' => 2026,
        ]);
    });

    // Original: test_depreciation_entry_updates_accumulated_depreciation. Real equivalent:
    // recording a period via the live service/controller updates accumulated_depreciation
    // and net_book_value on the AssetDepreciation row (and, through the asset's accessor,
    // on the FixedAsset itself).
    test('depreciation entry updates accumulated depreciation and net book value', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Accumulate Test Asset',
            'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 100000,
            'salvage_value' => 0,
            'useful_life_years' => 10,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->expenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/fixed-assets/{$asset->id}/depreciate", [
                'period' => '2026-02-01',
            ]);

        $response->assertCreated();
        expect((float) $response->json('depreciation_amount'))->toEqual(10000.0);
        expect((float) $response->json('accumulated_depreciation'))->toEqual(10000.0);
        expect((float) $response->json('net_book_value'))->toEqual(90000.0);

        $asset->refresh();
        expect((float) $asset->accumulated_depreciation)->toEqual(10000.0);
        expect((float) $asset->net_book_value)->toEqual(90000.0);
    });
});
