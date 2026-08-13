<?php

use Carbon\Carbon;
use Modules\Accounting\Models\AssetDisposal;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Services\FixedAssetService;

describe('Fixed Asset Management', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(FixedAssetService::class);

        // Create required chart of accounts for asset tests
        $this->assetAccount = ChartOfAccount::factory()->create([
            'name' => 'Fixed Assets',
        ]);
        $this->depreciationExpenseAccount = ChartOfAccount::factory()->create([
            'name' => 'Depreciation Expense',
        ]);
        $this->accumulatedDepreciationAccount = ChartOfAccount::factory()->create([
            'name' => 'Accumulated Depreciation',
        ]);
    });

    // ─── Asset creation ───────────────────────────────────────────────────────

    test('can create a fixed asset', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Office Equipment',
            'asset_class' => 'equipment',
            'acquisition_date' => '2025-01-01',
            'acquisition_cost' => 50000,
            'salvage_value' => 5000,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        expect($asset)->toBeInstanceOf(FixedAsset::class);
        expect($asset->status)->toBe('active');
        expect($asset->asset_code)->toStartWith('FA-');
    });

    test('depreciable amount = acquisition_cost - salvage_value', function () {
        $asset = FixedAsset::factory()->create([
            'acquisition_cost' => 100000,
            'salvage_value' => 10000,
        ]);

        expect($asset->depreciableAmount())->toEqual(90000.0);
    });

    // ─── Straight-line depreciation ───────────────────────────────────────────

    test('straight-line depreciation calculates correct monthly amount', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'SL Asset',
            'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 120000,
            'salvage_value' => 0,
            'useful_life_years' => 120,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $entry = $this->service->depreciate($asset, Carbon::parse('2026-02-01'));

        expect($entry)->not->toBeNull();
        expect((float) $entry->depreciation_amount)->toEqual(1000.0);
        expect((float) $entry->accumulated_depreciation)->toEqual(1000.0);
        expect((float) $entry->net_book_value)->toEqual(119000.0);
    });

    test('asset accumulates depreciation across multiple periods', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Multi-period Asset',
            'asset_class' => 'vehicle',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 60000,
            'salvage_value' => 0,
            'useful_life_years' => 60,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $this->service->depreciate($asset, Carbon::parse('2026-01-15'));
        $this->service->depreciate($asset, Carbon::parse('2026-02-15'));
        $this->service->depreciate($asset, Carbon::parse('2026-03-15'));

        $asset->refresh();
        expect((float) $asset->accumulated_depreciation)->toEqual(3000.0);
        expect((float) $asset->net_book_value)->toEqual(57000.0);
    });

    test('does not duplicate depreciation for same period', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Dup Test',
            'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 12000,
            'salvage_value' => 0,
            'useful_life_years' => 12,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $this->service->depreciate($asset, Carbon::parse('2026-02-01'));
        $second = $this->service->depreciate($asset, Carbon::parse('2026-02-15')); // same month

        expect($second)->toBeNull();
        expect($asset->fresh()->depreciations()->count())->toBe(1);
    });

    // ─── Declining balance ────────────────────────────────────────────────────

    test('declining-balance depreciation uses current NBV', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'DB Asset',
            'asset_class' => 'machinery',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 100000,
            'salvage_value' => 5000,
            'useful_life_years' => 60,
            'depreciation_method' => 'declining-balance',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $entry1 = $this->service->depreciate($asset, Carbon::parse('2026-02-01'));
        $entry2 = $this->service->depreciate($asset, Carbon::parse('2026-03-01'));

        // Second period uses lower NBV → smaller amount
        expect($entry2)->not->toBeNull();
        expect((float) $entry2->depreciation_amount)->toBeLessThan((float) $entry1->depreciation_amount);
    });

    // ─── Fully depreciated ────────────────────────────────────────────────────

    test('asset status becomes fully_depreciated when accumulated >= depreciable amount', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => '2-month asset',
            'asset_class' => 'computer',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 2000,
            'salvage_value' => 0,
            'useful_life_years' => 2,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $this->service->depreciate($asset, Carbon::parse('2026-02-01'));
        $this->service->depreciate($asset, Carbon::parse('2026-03-01'));

        $asset->refresh();
        expect($asset->status)->toBe('fully-depreciated');
        expect((float) $asset->net_book_value)->toEqual(0.0);
    });

    test('depreciation returns null for fully-depreciated asset', function () {
        $asset = FixedAsset::factory()->create([
            'status' => 'fully-depreciated',
        ]);

        $entry = $this->service->depreciate($asset, now());

        expect($entry)->toBeNull();
    });

    // ─── Depreciation schedule ────────────────────────────────────────────────

    test('depreciation schedule covers full useful life', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Schedule Asset',
            'asset_class' => 'fixture',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 24000,
            'salvage_value' => 0,
            'useful_life_years' => 24,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $schedule = $this->service->depreciationSchedule($asset);

        expect(count($schedule))->toBe(24);

        $last = end($schedule);
        expect((float) $last['net_book_value'])->toEqual(0.0);
        expect((float) $last['accumulated_depreciation'])->toEqual(24000.0);
    });

    test('each schedule entry has required keys', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Key Test', 'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01', 'acquisition_cost' => 12000,
            'salvage_value' => 0, 'useful_life_years' => 12,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $schedule = $this->service->depreciationSchedule($asset);

        expect($schedule[0])->toHaveKeys([
            'period', 'period_start', 'period_end',
            'depreciation_amount', 'accumulated_depreciation', 'net_book_value',
        ]);
    });

    // ─── Disposal ─────────────────────────────────────────────────────────────

    test('can dispose an asset with gain on sale', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Dispose Test', 'asset_class' => 'vehicle',
            'acquisition_date' => '2022-01-01', 'acquisition_cost' => 30000,
            'salvage_value' => 0, 'useful_life_years' => 5,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        // Force depreciate 48 months (4 years × 500/mo) → NBV = 30000 - 24000 = 6000
        for ($i = 1; $i <= 48; $i++) {
            $this->service->depreciate($asset, now()->subMonths(49 - $i));
        }
        $asset->refresh();

        $disposal = $this->service->dispose($asset, 'sale', 10000, now());

        expect($disposal)->toBeInstanceOf(AssetDisposal::class);
        expect((float) $disposal->gain_loss)->toBeGreaterThan(0);
        expect($asset->fresh()->status)->toBe('disposed');
    });

    test('can dispose an asset with loss on write-off', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Write-off Test', 'asset_class' => 'equipment',
            'acquisition_date' => '2025-01-01', 'acquisition_cost' => 30000,
            'salvage_value' => 0, 'useful_life_years' => 36,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $disposal = $this->service->dispose($asset, 'write_off', 0, now(), $this->user->id, 'Damaged beyond repair');

        expect((float) $disposal->disposal_proceeds)->toEqual(0.0);
        expect((float) $disposal->gain_loss)->toBeLessThanOrEqual(0);
    });

    // ─── API Endpoints ────────────────────────────────────────────────────────

    test('GET /api/v1/accounting/fixed-assets returns asset list', function () {
        FixedAsset::factory()->count(3)->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/fixed-assets');

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toHaveCount(3);
    });

    test('POST /api/v1/accounting/fixed-assets creates asset', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/fixed-assets', [
                'name' => 'API Asset',
                'asset_class' => 'equipment',
                'acquisition_date' => '2026-01-01',
                'acquisition_cost' => 25000,
                'salvage_value' => 2500,
                'useful_life_years' => 5,
                'depreciation_method' => 'straight-line',
                'asset_account_id' => $this->assetAccount->id,
                'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
                'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('name'))->toBe('API Asset');
        expect($response->json('status'))->toBe('active');
    });

    test('GET /api/v1/accounting/fixed-assets/{id}/schedule returns depreciation schedule', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Schedule API Test', 'asset_class' => 'equipment',
            'acquisition_date' => '2026-01-01', 'acquisition_cost' => 12000,
            'salvage_value' => 0, 'useful_life_years' => 12,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/fixed-assets/{$asset->id}/schedule");

        expect($response->status())->toBe(200);
        expect($response->json('schedule'))->toHaveCount(12);
        expect($response->json('total_periods'))->toBe(12);
    });

    test('POST /api/v1/accounting/fixed-assets/{id}/depreciate records depreciation', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Depreciate API Test', 'asset_class' => 'computer',
            'acquisition_date' => '2026-01-01', 'acquisition_cost' => 6000,
            'salvage_value' => 0, 'useful_life_years' => 12,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/fixed-assets/{$asset->id}/depreciate", [
                'period' => '2026-02-01',
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('depreciation_amount'))->toEqual(500.0);
    });

    test('POST /api/v1/accounting/fixed-assets/{id}/dispose disposes asset', function () {
        $asset = $this->service->create([
            'tenant_id' => 1,
            'name' => 'Dispose API Test', 'asset_class' => 'vehicle',
            'acquisition_date' => '2024-01-01', 'acquisition_cost' => 40000,
            'salvage_value' => 0, 'useful_life_years' => 60,
            'depreciation_method' => 'straight-line',
            'asset_account_id' => $this->assetAccount->id,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccount->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/fixed-assets/{$asset->id}/dispose", [
                'disposal_date' => today()->toDateString(),
                'disposal_type' => 'sale',
                'disposal_proceeds' => 15000,
                'notes' => 'Sold to third party',
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('disposal_type'))->toBe('sale');
        expect($response->json('disposal_proceeds'))->toEqual(15000.0);
    });

    test('GET /api/v1/accounting/fixed-assets/register returns asset register with totals', function () {
        FixedAsset::factory()->count(5)->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/fixed-assets/register');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['data', 'totals', 'count']);
        expect($response->json('totals'))->toHaveKeys([
            'acquisition_cost', 'accumulated_depreciation', 'net_book_value',
        ]);
        expect($response->json('count'))->toBe(5);
    });

    test('POST /api/v1/accounting/fixed-assets/depreciate-all runs batch depreciation', function () {
        FixedAsset::factory()->count(3)->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'acquisition_date' => '2025-01-01',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/fixed-assets/depreciate-all', [
                'period' => '2026-02-01',
            ]);

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['processed', 'skipped', 'errors']);
    });
});
