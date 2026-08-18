<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * Chantier 8.3 (HR): GET /hr threw "View [hr::dashboard] not found" on every
 * request (no such Blade view existed) — fixed to Inertia::render the real,
 * already-working HR/Dashboard.vue. Leave/Analytics.vue (real, reachable
 * page) called GET /api/v1/hr/leave-analytics, which didn't exist anywhere
 * and 404'd — built onto the same days_per_year-minus-taken formula
 * EmployeeSelfServiceController/EmployeePortalController already use for a
 * single employee's own balance, aggregated across everyone.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrActiveBreakageTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('hr dashboard root page renders instead of view-not-found', function () {
    $user = hrActiveBreakageTestUser();

    $response = test()->actingAs($user)->get('/hr');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('HR/Dashboard', false));
});

test('leave analytics endpoint returns real aggregated data', function () {
    $user = hrActiveBreakageTestUser();
    $employee = Employee::factory()->create(['status' => 'active']);
    $leaveType = LeaveType::factory()->create(['is_active' => true, 'days_per_year' => 20]);

    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
        'start_date' => now()->startOfYear()->addDays(10),
        'end_date' => now()->startOfYear()->addDays(12),
        'days' => 3,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/hr/leave-analytics?year='.now()->year);

    $response->assertOk();
    $json = $response->json();

    expect($json)->toHaveKeys([
        'total_taken', 'avg_balance', 'pending_requests', 'approval_rate',
        'monthly_trend', 'leave_type_stats', 'employee_balances',
    ]);
    expect($json['total_taken'])->toBe(3);
    expect($json['monthly_trend'])->toHaveCount(12);

    $balance = collect($json['employee_balances'])->firstWhere('employee_id', $employee->id);
    expect((float) $balance['taken'])->toBe(3.0);
});
