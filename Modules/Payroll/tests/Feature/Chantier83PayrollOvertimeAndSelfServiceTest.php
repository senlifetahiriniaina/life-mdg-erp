<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Services\PayrollIntegrationService;
use Modules\Timesheets\Models\TimesheetEntry;

function payrollOvertimeMakePayslip(array $overrides): Payslip
{
    $tenantId = $overrides['tenant_id'] ?? 1;
    $run = PayrollRun::where('tenant_id', $tenantId)
        ->whereDate('period', $overrides['period'])
        ->first()
        ?? PayrollRun::create([
            'tenant_id' => $tenantId,
            'period'    => $overrides['period'],
            'status'    => 'draft',
            'currency'  => $overrides['currency'] ?? 'XOF',
        ]);

    return Payslip::create(array_merge(['payroll_run_id' => $run->id], $overrides));
}

/**
 * Chantier 8.3 (Payroll) part 3: overtime now derives from real
 * TimesheetEntry hours (was reading a dead hr_timesheets stub table that
 * nothing ever wrote to), and PayrollPolicy's "employee views own payslip"
 * ability — previously unregistered with the Gate and referencing a
 * nonexistent 'payroll-manager' role — now has a real self-service
 * consumer.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function payrollOvertimeEmployee(float $baseSalary = 320_000): array
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    EmployeeCompensation::factory()->create([
        'employee_id'    => $employee->id,
        'base_salary'    => $baseSalary,
        'currency'       => 'XOF',
        'effective_date' => now()->startOfMonth()->subMonth(),
        'end_date'       => null,
    ]);

    return [$user, $employee];
}

test('overtime is derived from approved TimesheetEntry hours beyond the 160h/month standard', function () {
    [$user, $employee] = payrollOvertimeEmployee();

    TimesheetEntry::factory()->create([
        'employee_id'  => $employee->id,
        'entry_date'   => now()->startOfMonth()->addDays(2),
        'hours_worked' => 180,
        'status'       => 'approved',
    ]);
    // Not approved — must not count toward overtime.
    TimesheetEntry::factory()->create([
        'employee_id'  => $employee->id,
        'entry_date'   => now()->startOfMonth()->addDays(5),
        'hours_worked' => 50,
        'status'       => 'submitted',
    ]);

    $service = app(PayrollIntegrationService::class);
    $components = $service->calculateSalaryComponents($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($components['overtime']['hours'])->toBe(20.0);
});

test('an employee can view their own payslip but not another employee\'s', function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    [$ownerUser, $ownerEmployee] = payrollOvertimeEmployee();
    [$otherUser, $otherEmployee] = payrollOvertimeEmployee();
    $ownerUser->assignRole('employee');
    $otherUser->assignRole('employee');

    $payslip = payrollOvertimeMakePayslip([
        'tenant_id'    => 1,
        'employee_id'  => $ownerEmployee->id,
        'employee_name'=> 'Test Employee',
        'period'       => now()->startOfMonth(),
        'gross_salary' => 320_000,
        'total_deductions' => 0,
        'net_salary'   => 320_000,
        'currency'     => 'XOF',
        'status'       => 'draft',
    ]);

    test()->actingAs($ownerUser, 'sanctum')
        ->getJson("/api/v1/payroll/payslips/{$payslip->id}")
        ->assertOk();

    test()->actingAs($otherUser, 'sanctum')
        ->getJson("/api/v1/payroll/payslips/{$payslip->id}")
        ->assertForbidden();
});

test('myPayslips lists only the authenticated employee\'s own payslips', function () {
    [$user, $employee] = payrollOvertimeEmployee();

    payrollOvertimeMakePayslip([
        'tenant_id' => 1, 'employee_id' => $employee->id, 'employee_name' => 'Me',
        'period' => now()->startOfMonth(), 'gross_salary' => 320_000, 'total_deductions' => 0,
        'net_salary' => 320_000, 'currency' => 'XOF', 'status' => 'draft',
    ]);
    [, $otherEmployee] = payrollOvertimeEmployee();
    payrollOvertimeMakePayslip([
        'tenant_id' => 1, 'employee_id' => $otherEmployee->id, 'employee_name' => 'Other',
        'period' => now()->startOfMonth(), 'gross_salary' => 300_000, 'total_deductions' => 0,
        'net_salary' => 300_000, 'currency' => 'XOF', 'status' => 'draft',
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/me/payslips')->assertOk();

    $ids = collect($response->json('payslips.data'))->pluck('employee_id');
    expect($ids->unique()->all())->toBe([$employee->id]);
});
