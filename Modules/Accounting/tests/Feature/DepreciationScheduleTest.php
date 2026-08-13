<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\DepreciationSchedule;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Models\GlAccount;
use Tests\TestCase;

class DepreciationScheduleTest extends TestCase
{
    protected User $user;
    protected Company $company;
    protected FixedAsset $fixedAsset;
    protected GlAccount $expenseAccount;
    protected GlAccount $accumulatedAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        $this->fixedAsset = FixedAsset::factory()->for($this->company)->create();
        $this->expenseAccount = GlAccount::factory()->for($this->company)->create();
        $this->accumulatedAccount = GlAccount::factory()->for($this->company)->create();

        $this->user->givePermissionTo('accounting.depreciation.view');
        $this->user->givePermissionTo('accounting.depreciation.create');
        $this->user->givePermissionTo('accounting.depreciation.update');
        $this->user->givePermissionTo('accounting.depreciation.record');
    }

    public function test_can_create_depreciation_schedule(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/depreciation-schedules', [
                'fixed_asset_id' => $this->fixedAsset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 10,
                'residual_value' => 5000.00,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 9500.00,
                'depreciation_expense_account_id' => $this->expenseAccount->id,
                'accumulated_depreciation_account_id' => $this->accumulatedAccount->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('depreciation_schedules', [
            'fixed_asset_id' => $this->fixedAsset->id,
            'depreciation_method' => 'straight_line',
            'status' => 'active',
        ]);
    }

    public function test_can_list_depreciation_schedules(): void
    {
        DepreciationSchedule::factory()
            ->for($this->fixedAsset)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/accounting/depreciation-schedules');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_view_depreciation_schedule(): void
    {
        $schedule = DepreciationSchedule::factory()
            ->for($this->fixedAsset)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/accounting/depreciation-schedules/{$schedule->id}");

        $response->assertOk()
            ->assertJsonPath('data.depreciation_method', $schedule->depreciation_method);
    }

    public function test_can_record_depreciation_entry(): void
    {
        $schedule = DepreciationSchedule::factory()
            ->for($this->fixedAsset)
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/accounting/depreciation-schedules/{$schedule->id}/record", [
                'period_date' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('depreciation_entries', [
            'depreciation_schedule_id' => $schedule->id,
            'status' => 'recorded',
        ]);
    }

    public function test_depreciation_entry_updates_accumulated_depreciation(): void
    {
        $schedule = DepreciationSchedule::factory()
            ->for($this->fixedAsset)
            ->create([
                'status' => 'active',
                'annual_depreciation_amount' => 10000.00,
                'accumulated_depreciation' => 0,
                'book_value' => 100000.00,
            ]);

        $this->actingAs($this->user)
            ->postJson("/api/accounting/depreciation-schedules/{$schedule->id}/record", [
                'period_date' => now()->toDateString(),
            ]);

        $this->assertDatabaseHas('depreciation_entries', [
            'depreciation_schedule_id' => $schedule->id,
            'depreciation_amount' => 10000.00,
            'accumulated_depreciation' => 10000.00,
            'book_value' => 90000.00,
        ]);
    }
}
