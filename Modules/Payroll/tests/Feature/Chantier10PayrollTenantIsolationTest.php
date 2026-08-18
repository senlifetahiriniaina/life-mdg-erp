<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Payroll\Models\Payslip;

/**
 * Chantier 10 (Payroll headline finding): PayrollController::tenantId()
 * read $request->user()->tenant_id — the well-documented phantom column
 * (real, migrated, never in User::$fillable, never populated by the real
 * registration flow) already fixed repeatedly elsewhere in this session
 * (Reporting, Strategy, AI, Sales, Achats, Integration, Workflow). Since
 * tenant_id is effectively always null/0 in production, every company's
 * payslips were silently collapsing into one shared tenant_id=0 bucket —
 * any payroll-officer/hr-manager/admin from Company A could list, approve,
 * and pay Company B's payslips. Fixed to users.company_id. This test locks
 * in real cross-tenant isolation over the real HTTP path with two distinct
 * companies, matching the Chantier83LogisticsRbacTest pattern.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function payrollUser(Company $company, string $role): User
{
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

test('a payroll officer from company A cannot list company B payslips', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = payrollUser($companyA, 'payroll-officer');

    $period = now()->startOfMonth()->toDateString();

    Payslip::factory()->create([
        'tenant_id' => $companyA->id,
        'period'    => $period,
        'status'    => 'draft',
    ]);
    $payslipB = Payslip::factory()->create([
        'tenant_id' => $companyB->id,
        'period'    => $period,
        'status'    => 'draft',
    ]);

    $response = test()->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/payroll/payslips?period='.now()->format('Y-m'));

    $response->assertOk();
    $ids = collect($response->json('payslips.data'))->pluck('id');

    expect($ids)->not->toContain($payslipB->id);
});

test('approve-batch for company A never touches company B draft payslips', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = payrollUser($companyA, 'payroll-officer');

    $period = now()->startOfMonth()->toDateString();

    $payslipA = Payslip::factory()->create([
        'tenant_id' => $companyA->id,
        'period'    => $period,
        'status'    => 'draft',
    ]);
    $payslipB = Payslip::factory()->create([
        'tenant_id' => $companyB->id,
        'period'    => $period,
        'status'    => 'draft',
    ]);

    $response = test()->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/payroll/payslips/approve-batch', ['period' => now()->format('Y-m')]);

    $response->assertOk();
    expect($response->json('approved_count'))->toBe(1);

    expect($payslipA->fresh()->status)->toBe('approved');
    expect($payslipB->fresh()->status)->toBe('draft');
});

test('payroll statistics for company A do not aggregate company B gross salary', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = payrollUser($companyA, 'payroll-officer');

    $period = now()->startOfMonth()->toDateString();

    Payslip::factory()->create([
        'tenant_id'    => $companyA->id,
        'period'       => $period,
        'gross_salary' => 100_000,
    ]);
    Payslip::factory()->create([
        'tenant_id'    => $companyB->id,
        'period'       => $period,
        'gross_salary' => 9_000_000,
    ]);

    $response = test()->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/payroll/statistics?period='.now()->format('Y-m'));

    $response->assertOk();
    // Company B's much larger gross salary must never leak into A's stats.
    $payload = json_encode($response->json());
    expect($payload)->not->toContain('9000000');
});
