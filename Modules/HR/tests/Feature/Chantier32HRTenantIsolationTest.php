<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\SalaryBand;
use Modules\HR\Models\Skill;
use Modules\HR\Models\AttendanceRecord;
use Modules\Payroll\Services\PayrollIntegrationService;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 32 — HR module had **zero** company-based tenant isolation
 * anywhere at all: no company_id column on any core table, no
 * per-record sameCompany check on any of its 7 policies, no role gate on
 * its web layer, and a Payroll payslip generator that pulled in every
 * tenant's employees for any given payroll run. Confirmed empirically
 * (and previously locked in as a standing regression fixture — see the
 * rewritten Chantier19HRReauditTest.php) that a Company A hr-manager could
 * list/view/edit/delete Company B's employees, departments, leave
 * requests, and every other HR resource.
 *
 * This test locks in the fix, over the real HTTP path, mirroring
 * Modules\CRM\tests\Feature\Chantier19CrmReauditTest.php's structure.
 */
function hrReauditUser(string $companySuffix, string $role = 'hr-manager'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = Company::create([
        'name' => "Chantier32 HR Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ── index() company-scoped listings ────────────────────────────────────────

test('employees index only returns the caller company own employees', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    Employee::factory()->create(['company_id' => $userA->company_id, 'first_name' => 'Alice']);
    Employee::factory()->create(['company_id' => $userB->company_id, 'first_name' => 'Bob']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/employees')->assertOk();

    $names = collect($resp->json('data'))->pluck('first_name');
    expect($names)->toContain('Alice')->not->toContain('Bob');
});

test('departments index only returns the caller company own departments', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    Department::factory()->create(['company_id' => $userA->company_id, 'name' => 'Finance A']);
    Department::factory()->create(['company_id' => $userB->company_id, 'name' => 'Finance B']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/departments')->assertOk();

    $names = collect($resp->json('data'))->pluck('name');
    expect($names)->toContain('Finance A')->not->toContain('Finance B');
});

test('job positions index only returns the caller company own positions', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    JobPosition::factory()->create(['company_id' => $userA->company_id, 'title' => 'Engineer A']);
    JobPosition::factory()->create(['company_id' => $userB->company_id, 'title' => 'Engineer B']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/job-positions')->assertOk();

    // Note: JobPositionController::index() returns response()->json($resourceCollection)
    // directly (bypassing Responsable::toResponse()), so the payload is a flat array,
    // not wrapped under a 'data' key like EmployeeController's response.
    $titles = collect($resp->json())->pluck('title');
    expect($titles)->toContain('Engineer A')->not->toContain('Engineer B');
});

test('leave types index only returns the caller company own leave types', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    LeaveType::factory()->create(['company_id' => $userA->company_id, 'name' => 'Congé A']);
    LeaveType::factory()->create(['company_id' => $userB->company_id, 'name' => 'Congé B']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/leave-types')->assertOk();

    // Note: LeaveTypeController::index() also returns response()->json($resourceCollection)
    // directly — same unwrapped flat-array shape as JobPositionController above.
    $names = collect($resp->json())->pluck('name');
    expect($names)->toContain('Congé A')->not->toContain('Congé B');
});

test('salary bands index only returns the caller company own bands', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    SalaryBand::factory()->create(['company_id' => $userA->company_id, 'title' => 'Band A']);
    SalaryBand::factory()->create(['company_id' => $userB->company_id, 'title' => 'Band B']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/salary-bands')->assertOk();

    $titles = collect($resp->json())->pluck('title');
    expect($titles)->toContain('Band A')->not->toContain('Band B');
});

test('skills index only returns the caller company own skills', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    Skill::factory()->create(['company_id' => $userA->company_id, 'name' => 'Skill A']);
    Skill::factory()->create(['company_id' => $userB->company_id, 'name' => 'Skill B']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/skills')->assertOk();

    $names = collect($resp->json('data'))->pluck('name');
    expect($names)->toContain('Skill A')->not->toContain('Skill B');
});

test('leave requests index only returns the caller company own requests', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    $employeeA = Employee::factory()->create(['company_id' => $userA->company_id]);
    $employeeB = Employee::factory()->create(['company_id' => $userB->company_id]);

    LeaveRequest::factory()->create(['company_id' => $userA->company_id, 'employee_id' => $employeeA->id, 'reason' => 'From A']);
    LeaveRequest::factory()->create(['company_id' => $userB->company_id, 'employee_id' => $employeeB->id, 'reason' => 'From B']);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/leave-requests')->assertOk();

    $reasons = collect($resp->json('data'))->pluck('reason');
    expect($reasons)->toContain('From A')->not->toContain('From B');
});

test('attendance admin index only returns the caller company own attendance records', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    $employeeA = Employee::factory()->create(['company_id' => $userA->company_id]);
    $employeeB = Employee::factory()->create(['company_id' => $userB->company_id]);

    AttendanceRecord::factory()->create(['company_id' => $userA->company_id, 'employee_id' => $employeeA->id]);
    AttendanceRecord::factory()->create(['company_id' => $userB->company_id, 'employee_id' => $employeeB->id]);

    $resp = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/hr/attendance-records')->assertOk();

    $employeeIds = collect($resp->json('data'))->pluck('employee_id');
    expect($employeeIds)->toContain($employeeA->id)->not->toContain($employeeB->id);
});

// ── show/update/destroy: cross-company denial ──────────────────────────────

test('company B cannot view, update, or delete company A employee', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    $employeeA = Employee::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/hr/employees/{$employeeA->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/hr/employees/{$employeeA->id}", ['first_name' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/hr/employees/{$employeeA->id}")->assertForbidden();

    // Company A itself is unaffected.
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/hr/employees/{$employeeA->id}")->assertOk();
});

test('company B cannot view, update, approve, or delete company A leave request', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    $employeeA = Employee::factory()->create(['company_id' => $userA->company_id]);
    $leaveRequestA = LeaveRequest::factory()->create([
        'company_id' => $userA->company_id,
        'employee_id' => $employeeA->id,
        'status' => 'pending',
    ]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/hr/leave-requests/{$leaveRequestA->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/hr/leave-requests/{$leaveRequestA->id}", ['reason' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/hr/leave-requests/{$leaveRequestA->id}/approve")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/hr/leave-requests/{$leaveRequestA->id}")->assertForbidden();

    // Company A's own hr-manager can still approve it.
    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/hr/leave-requests/{$leaveRequestA->id}/approve")->assertOk();
});

// ── store(): client-supplied company_id is ignored ─────────────────────────

test('creating an employee ignores a client-supplied company_id and uses the caller own company', function () {
    $userA = hrReauditUser('A');
    $otherCompany = Company::create(['name' => 'Someone Else Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);

    $dept = Department::factory()->create(['company_id' => $userA->company_id]);
    $jobPosition = JobPosition::factory()->create(['company_id' => $userA->company_id]);

    $resp = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/hr/employees', [
        'employee_number' => 'EMP-SPOOF-'.uniqid(),
        'first_name' => 'Spoofed',
        'last_name' => 'Employee',
        'email' => 'spoofed.'.uniqid().'@example.com',
        'department_id' => $dept->id,
        'job_position_id' => $jobPosition->id,
        'hire_date' => now()->toDateString(),
        'company_id' => $otherCompany->id, // client-supplied, must be ignored
    ]);

    $resp->assertCreated();

    $employee = Employee::where('email', 'like', 'spoofed.%')->firstOrFail();
    expect((int) $employee->company_id)->toBe((int) $userA->company_id);
    expect((int) $employee->company_id)->not->toBe((int) $otherCompany->id);
});

// ── Web layer: role gate ────────────────────────────────────────────────────

test('an authenticated user without any HR-eligible role is rejected from the HR web layer', function () {
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = Company::create(['name' => 'No HR Role Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('sales-rep'); // a real role with no hr.* permission

    $resp = test()->actingAs($user)->get('/hr/employees');

    expect($resp->status())->toBeIn([403, 302]);
});

test('an HR-eligible role reaches the HR web employees page', function () {
    $userA = hrReauditUser('A', 'hr-manager');

    $resp = test()->actingAs($userA)->get('/hr/employees');

    $resp->assertOk();
});

// ── Payroll cross-tenant fix ─────────────────────────────────────────────

test('generatePayslips only pulls in the given tenant own active employees', function () {
    $userA = hrReauditUser('A');
    $userB = hrReauditUser('B');

    $employeeA = Employee::factory()->create(['company_id' => $userA->company_id, 'status' => 'active']);
    $employeeB = Employee::factory()->create(['company_id' => $userB->company_id, 'status' => 'active']);

    \Modules\HR\Models\EmployeeCompensation::factory()->create([
        'employee_id' => $employeeA->id,
        'base_salary' => 1000000,
        'effective_date' => now()->subMonth(),
    ]);
    \Modules\HR\Models\EmployeeCompensation::factory()->create([
        'employee_id' => $employeeB->id,
        'base_salary' => 2000000,
        'effective_date' => now()->subMonth(),
    ]);

    $service = app(PayrollIntegrationService::class);
    $result = $service->generatePayslips(
        (int) $userA->company_id,
        now()->startOfMonth(),
        now()->endOfMonth(),
    );

    $payslips = \Modules\Payroll\Models\Payslip::whereIn('id', $result['payslip_ids'])->get();
    $payslipEmployeeIds = $payslips->pluck('employee_id')->all();

    expect($payslipEmployeeIds)->toContain($employeeA->id);
    expect($payslipEmployeeIds)->not->toContain($employeeB->id);
});
