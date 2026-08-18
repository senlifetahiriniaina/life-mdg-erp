<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;

/**
 * Chantier 8.3 (HR): CompensationService (base salary/bonus/benefits/equity
 * vesting tracking) was fully written, real, and schema-correct, but had
 * zero routes — wired for the first time via CompensationController.
 * generateOfferLetterData()/getBenefitsDetails() were deliberately dropped
 * (ATS/recruitment-adjacent, out of scope — see CLAUDE.md).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrCompensationUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('hr-manager');

    return $user;
}

test('creating a compensation record computes total compensation', function () {
    $user = hrCompensationUser();
    $employee = Employee::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/employees/{$employee->id}/compensation", [
        'base_salary' => 1000000,
        'bonus_amount' => 100000,
        'benefits_annual_value' => 50000,
        'effective_date' => now()->toDateString(),
    ]);

    $response->assertCreated();
    expect((float) $response->json('total_compensation'))->toBe(1150000.0);
});

test('breakdown and current endpoints return the active compensation record', function () {
    $user = hrCompensationUser();
    $employee = Employee::factory()->create();
    EmployeeCompensation::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => 800000,
        'bonus_amount' => 0,
        'benefits_annual_value' => 0,
        'equity_granted' => 0,
        'effective_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $current = test()->actingAs($user, 'sanctum')->getJson("/api/v1/hr/employees/{$employee->id}/compensation/current")
        ->assertOk();
    expect((float) $current->json('base_salary'))->toBe(800000.0);

    $breakdown = test()->actingAs($user, 'sanctum')->getJson("/api/v1/hr/employees/{$employee->id}/compensation/breakdown")
        ->assertOk();
    expect((float) $breakdown->json('total'))->toBe(800000.0);
});

test('benchmark comparison flags below-market compensation', function () {
    $user = hrCompensationUser();
    $employee = Employee::factory()->create();
    EmployeeCompensation::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => 500000,
        'bonus_amount' => 0,
        'benefits_annual_value' => 0,
        'equity_granted' => 0,
        'effective_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/hr/employees/{$employee->id}/compensation/benchmark", ['market_median' => 1000000]);

    $response->assertOk();
    expect($response->json('below_market'))->toBeTrue();
});

test('audit endpoint flags active employees without a compensation record', function () {
    $user = hrCompensationUser();
    Employee::factory()->create(['status' => 'active']);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/compensation/audit');

    $response->assertOk();
    $issues = collect($response->json('issues'));
    expect($issues->firstWhere('type', 'missing_compensation'))->not->toBeNull();
});
