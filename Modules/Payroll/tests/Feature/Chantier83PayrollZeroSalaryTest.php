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
 * The real salary source is EmployeeCompensation. These tests lock in the
 * fix against the real write paths, not a factory bypass.
 *
 * Chantier 10 correction: the tenant source used to be documented as the
 * linked User's tenant_id — that was itself the same phantom-column bug
 * already fixed repeatedly elsewhere in this app (Reporting, Strategy, AI,
 * Sales, Achats, Integration, Workflow, Setup): users.tenant_id is a real,
 * migrated column but is never in User::$fillable and never populated by
 * any real registration/onboarding path, so every tenant's payslips were
 * silently collapsing into one shared bucket. Fixed to users.company_id;
 * these fixtures now set company_id instead of tenant_id to match.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('payslip generation uses the real EmployeeCompensation base salary, not the phantom Employee column', function () {
    $company = \App\Models\Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
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
    expect((int) $payslip->tenant_id)->toBe($company->id);
});

// Chantier 32: at the time this test was written, Employee had no real
// company_id column at all — generatePayslips() had to skip tenant
// filtering entirely (hence the original title, "...regardless of the
// phantom Employee.tenant_id column"). Employee now has a real, populated
// company_id column (see Modules\HR\Policies\EmployeePolicy's docblock) and
// generatePayslips() filters by it for real — the fixture below now sets it
// to match the tenant id passed in, and Chantier32HRTenantIsolationTest.php
// (Modules/HR) separately locks in that a DIFFERENT company's employee is
// correctly excluded.
test('generatePayslips finds active employees with a matching company_id', function () {
    $company = \App\Models\Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create([
        'user_id'          => $user->id,
        'company_id'       => $company->id,
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
    $result  = $service->generatePayslips($company->id, now()->startOfMonth(), now()->endOfMonth());

    expect($result['created_count'])->toBeGreaterThanOrEqual(1);
});

test('an employee with no EmployeeCompensation record gets a zero-salary payslip, not a crash', function () {
    $employee = Employee::factory()->create(['status' => 'active', 'termination_date' => null]);

    $service = app(PayrollIntegrationService::class);
    $payslip = $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($payslip)->not->toBeNull();
    expect((float) $payslip->gross_salary)->toBe(0.0);
});
