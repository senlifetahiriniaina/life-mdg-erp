<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Services\PayrollIntegrationService;

/**
 * Chantier 8.3 (Payroll headline finding): calculateSalaryComponents()
 * read base_salary/tenant_id/currency straight off Employee, but none of
 * those are in Employee's $fillable — no real create()/update() path ever
 * populates them, so every real payslip silently computed to (near) zero.
 * The real salary source is EmployeeCompensation; the real tenant source is
 * the linked User's tenant_id. These tests lock in the fix against the real
 * write paths, not a factory bypass.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('payslip generation uses the real EmployeeCompensation base salary, not the phantom Employee column', function () {
    $user = User::factory()->create(['tenant_id' => 7]);
    $employee = Employee::factory()->create([
        'user_id'          => $user->id,
        'status'           => 'active',
        'termination_date' => null,
    ]);
    EmployeeCompensation::factory()->create([
        'employee_id'    => $employee->id,
        'base_salary'    => 750_000,
        'currency'       => 'XOF',
        'effective_date' => now()->startOfMonth()->subMonth(),
        'end_date'       => null,
    ]);

    $service = app(PayrollIntegrationService::class);
    $payslip = $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($payslip)->not->toBeNull();
    expect((float) $payslip->gross_salary)->toBeGreaterThanOrEqual(750_000.0);
    expect($payslip->currency)->toBe('XOF');
    expect((int) $payslip->tenant_id)->toBe(7);
});

test('generatePayslips finds active employees regardless of the phantom Employee.tenant_id column', function () {
    $user = User::factory()->create(['tenant_id' => 3]);
    $employee = Employee::factory()->create([
        'user_id'          => $user->id,
        'status'           => 'active',
        'termination_date' => null,
    ]);
    EmployeeCompensation::factory()->create([
        'employee_id'    => $employee->id,
        'base_salary'    => 300_000,
        'currency'       => 'XOF',
        'effective_date' => now()->startOfMonth()->subMonth(),
        'end_date'       => null,
    ]);

    $service = app(PayrollIntegrationService::class);
    $result  = $service->generatePayslips(3, now()->startOfMonth(), now()->endOfMonth());

    expect($result['created_count'])->toBeGreaterThanOrEqual(1);
});

test('an employee with no EmployeeCompensation record gets a zero-salary payslip, not a crash', function () {
    $employee = Employee::factory()->create(['status' => 'active', 'termination_date' => null]);

    $service = app(PayrollIntegrationService::class);
    $payslip = $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($payslip)->not->toBeNull();
    expect((float) $payslip->gross_salary)->toBe(0.0);
});
