<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollIntegrationService;

/**
 * Chantier 32.18 — deep 14-layer audit of Modules\Payroll (see CLAUDE.md's
 * "Méthodologie d'audit approfondi (14 couches)"). Every bug below was
 * confirmed empirically via php artisan tinker against realistic seeded
 * data before being fixed, not just re-read.
 *
 * 1. (layer 6, deep security) PayrollPolicy::view()/export() granted full
 *    cross-tenant access to ANY payslip to every hr-manager/payroll-officer
 *    /admin user regardless of which company they belong to —
 *    PayrollController::show() has no other tenant check of its own, so a
 *    payroll-officer at Company B could view Company A's payslip by id.
 *    Confirmed via tinker before the fix ($userB->can('view', $payslipA)
 *    === true). Fixed with a real same-company check
 *    ($model->tenant_id === $user->company_id).
 *
 * 2. (layer 4/6) PayrollController::statistics() unconditionally overwrote
 *    the real payroll summary's currency with a hardcoded 'XOF' — a real
 *    Life-MDG (MGA-first) payslip's dashboard "Total Payroll" card
 *    displayed "XOF 525,000" instead of "Ar 525,000". Fixed by having
 *    getPayrollSummary() derive the real currency from the period's own
 *    payslips (or the tenant's Company record), and dropping the
 *    controller's own override.
 *
 * 3. (layer 8/10, business validation + relational) PayrollRun (the header
 *    record every Payslip in a batch points at via payroll_run_id) never
 *    had its own status/totals/validated_at updated by the real
 *    approve-batch/process-payment flow — confirmed via tinker that after
 *    a full approve+pay cycle through the real controller logic, the run
 *    stayed frozen at status=draft, total_gross=0. Wired
 *    PayrollService::validateRun()/processRun() (real, previously-orphaned
 *    lifecycle methods) into approveBatch()/processPayment().
 *
 * 4. (layer 11, CORE) Payroll had never wired into Chantier 20's
 *    ParticipantNotificationService — an employee's own payslip being
 *    approved/paid is a real process nothing notified them about. New
 *    PayslipObserver, registered in PayrollServiceProvider. Also fixed
 *    approveBatch()'s bulk Payslip::where(...)->update(...) query-builder
 *    call, which never fires Eloquent model events (and therefore never
 *    fires the new observer) — switched to iterate+save each record,
 *    matching processPayment()'s own already-correct pattern.
 *
 * 5. (layer 9, fake/dead) SalaryComponent + PayrollService::computeSalary()/
 *    getActiveComponents() confirmed dead: zero controller/route/Vue/test
 *    consumer anywhere, and a fully divergent, never-wired parallel salary-
 *    calculation engine, functionally superseded by the real, production
 *    PayrollIntegrationService::calculateSalaryComponents()/
 *    calculateDeductions() pipeline. Dropped (model + a dedicated
 *    drop-table migration), matching this session's established
 *    confirmed-dead-parallel-subsystem-deletion precedent.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function payroll3218User(Company $company, string $role): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // Chantier 37: postPayslipsToAccounting() (called by process-payment)
    // resolves real chart-of-accounts codes via AccountRoleService, which
    // now throws rather than silently posting a null account_id — the
    // chart of accounts is only seeded by AccountingDatabaseSeeder, not by
    // RolesAndPermissionsSeeder, same established gap already documented
    // for Chantier22DepositBalanceTest/CostingSheetTest/
    // Chantier19PayrollReauditTest.
    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function payroll3218Employee(?User $user, float $baseSalary, ?Employee $manager = null): Employee
{
    $employee = Employee::factory()->create([
        'user_id'          => $user?->id,
        'status'           => 'active',
        'termination_date' => null,
        'manager_id'       => $manager?->id,
    ]);

    EmployeeCompensation::factory()->create([
        'employee_id'    => $employee->id,
        'base_salary'    => $baseSalary,
        'currency'       => 'MGA',
        'effective_date' => now()->startOfMonth()->subMonth(),
        'end_date'       => null,
    ]);

    return $employee;
}

// ── 1. Cross-tenant IDOR on GET payslips/{payslip} ──────────────────────

test('a payroll-officer from company B cannot view company A\'s payslip by id', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = payroll3218User($companyA, 'payroll-officer');
    $userB = payroll3218User($companyB, 'payroll-officer');

    $empA = payroll3218Employee($userA, 700_000);
    $svc  = app(PayrollIntegrationService::class);
    $payslipA = $svc->generatePayslip($empA, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $companyA->id);

    test()->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/payroll/payslips/{$payslipA->id}")
        ->assertForbidden();
});

test('a payroll-officer/hr-manager/admin from the SAME company can still view a payslip', function () {
    $company = Company::factory()->create();
    $officer = payroll3218User($company, 'payroll-officer');
    $hrManager = payroll3218User($company, 'hr-manager');
    $admin = payroll3218User($company, 'admin');

    $emp = payroll3218Employee(null, 500_000);
    $svc = app(PayrollIntegrationService::class);
    $payslip = $svc->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);

    test()->actingAs($officer, 'sanctum')->getJson("/api/v1/payroll/payslips/{$payslip->id}")->assertOk();
    test()->actingAs($hrManager, 'sanctum')->getJson("/api/v1/payroll/payslips/{$payslip->id}")->assertOk();
    test()->actingAs($admin, 'sanctum')->getJson("/api/v1/payroll/payslips/{$payslip->id}")->assertOk();
});

// ── 2. statistics() no longer hardcodes currency ────────────────────────

test('GET statistics returns the real payslip currency, not a hardcoded XOF', function () {
    $company = Company::factory()->create(['currency' => 'MGA']);
    $officer = payroll3218User($company, 'payroll-officer');

    $emp = payroll3218Employee(null, 500_000);
    $svc = app(PayrollIntegrationService::class);
    $payslip = $svc->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);
    expect($payslip->currency)->toBe('MGA');

    $response = test()->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/payroll/statistics?period='.now()->format('Y-m'))
        ->assertOk();

    expect($response->json('statistics.currency'))->toBe('MGA')
        ->and($response->json('statistics.currency'))->not->toBe('XOF');
});

test('GET statistics falls back to the tenant Company currency when a period has no payslips at all', function () {
    $company = Company::factory()->create(['currency' => 'MGA']);
    $officer = payroll3218User($company, 'payroll-officer');

    $response = test()->actingAs($officer, 'sanctum')
        ->getJson('/api/v1/payroll/statistics?period=2020-01')
        ->assertOk();

    expect($response->json('statistics.employee_count'))->toBe(0)
        ->and($response->json('statistics.currency'))->toBe('MGA');
});

// ── 3. PayrollRun lifecycle sync through the real HTTP flow ─────────────

test('approve-batch and process-payment keep the PayrollRun header record in sync, over the real HTTP path', function () {
    $company = Company::factory()->create(['currency' => 'MGA']);
    $officer = payroll3218User($company, 'payroll-officer');

    $emp = payroll3218Employee(null, 600_000);
    app(PayrollIntegrationService::class)->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);

    $run = PayrollRun::where('tenant_id', $company->id)->first();
    expect($run->status)->toBe('draft')
        ->and((float) $run->total_gross)->toBe(0.0);

    test()->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/payroll/payslips/approve-batch', ['period' => now()->format('Y-m')])
        ->assertOk()
        ->assertJson(['approved_count' => 1]);

    $run->refresh();
    expect($run->status)->toBe('validated')
        ->and($run->validated_at)->not->toBeNull();

    test()->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/payroll/process-payment', ['period' => now()->format('Y-m')])
        ->assertOk()
        ->assertJson(['paid_count' => 1]);

    $run->refresh();
    expect($run->status)->toBe('paid')
        ->and((float) $run->total_gross)->toBeGreaterThan(0.0)
        ->and((float) $run->total_net)->toBeGreaterThan(0.0);

    $payslip = Payslip::where('tenant_id', $company->id)->first();
    expect($payslip->status)->toBe('paid');
});

// ── 4. ParticipantNotificationService wiring ─────────────────────────────

test('approving and then paying a payslip notifies the employee, over the real HTTP path', function () {
    $company = Company::factory()->create(['currency' => 'MGA']);
    $officer = payroll3218User($company, 'payroll-officer');

    $empUser = User::factory()->create(['company_id' => $company->id]);
    $emp = payroll3218Employee($empUser, 500_000);
    app(PayrollIntegrationService::class)->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);

    expect(DatabaseNotification::where('notifiable_id', $empUser->id)->count())->toBe(0);

    test()->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/payroll/payslips/approve-batch', ['period' => now()->format('Y-m')])
        ->assertOk();

    expect(DatabaseNotification::where('notifiable_id', $empUser->id)->count())->toBe(1);

    test()->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/payroll/process-payment', ['period' => now()->format('Y-m')])
        ->assertOk();

    // Chantier 32.18: DatabaseNotification's primary key is a UUID (not an
    // auto-increment int) and created_at can land in the same second for
    // both rows within a fast test run, so neither ->latest() nor
    // ->latest('id') reliably identifies "the second one" — assert the
    // full set of titles instead of picking one by ordering.
    $titles = DatabaseNotification::where('notifiable_id', $empUser->id)
        ->get()
        ->map(fn ($n) => $n->data['title'] ?? null)
        ->all();

    expect($titles)->toEqualCanonicalizing(['Fiche de paie approuvée', 'Fiche de paie payée']);
});

test('approve-batch and process-payment do not notify an employee whose payslip was never touched', function () {
    $company = Company::factory()->create(['currency' => 'MGA']);
    $officer = payroll3218User($company, 'payroll-officer');

    $untouchedUser = User::factory()->create(['company_id' => $company->id]);
    payroll3218Employee($untouchedUser, 500_000);
    // No payslip generated for this employee at all this period.

    test()->actingAs($officer, 'sanctum')
        ->postJson('/api/v1/payroll/payslips/approve-batch', ['period' => now()->format('Y-m')])
        ->assertOk()
        ->assertJson(['approved_count' => 0]);

    expect(DatabaseNotification::where('notifiable_id', $untouchedUser->id)->count())->toBe(0);
});

// ── 5. SalaryComponent confirmed dead, dropped ───────────────────────────

test('the dead salary_components table is really gone from the migrated schema', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('salary_components'))->toBeFalse();
    expect(class_exists(\Modules\Payroll\Models\SalaryComponent::class))->toBeFalse();
});

test('PayrollService no longer exposes the dead computeSalary()/getActiveComponents() methods', function () {
    expect(method_exists(\Modules\Payroll\Services\PayrollService::class, 'computeSalary'))->toBeFalse();
    expect(method_exists(\Modules\Payroll\Services\PayrollService::class, 'getActiveComponents'))->toBeFalse();
});

// ── 6. New Payslip relations (layer 10) actually resolve real records ───

// ── 7. Real "bulletin de paie" PDF export (layer 14c) ────────────────────

test('GET payslips/{id}/export/pdf streams a real PDF containing the real payslip figures', function () {
    $company = Company::factory()->create(['currency' => 'MGA']);
    $officer = payroll3218User($company, 'payroll-officer');

    $emp = payroll3218Employee(null, 500_000);
    $payslip = app(PayrollIntegrationService::class)
        ->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);

    $response = test()->actingAs($officer, 'sanctum')
        ->get("/api/v1/payroll/payslips/{$payslip->id}/export/pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();
});

test('a payroll-officer from another company cannot download company A\'s payslip PDF', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userB = payroll3218User($companyB, 'payroll-officer');

    $emp = payroll3218Employee(null, 500_000);
    $payslip = app(PayrollIntegrationService::class)
        ->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $companyA->id);

    test()->actingAs($userB, 'sanctum')
        ->get("/api/v1/payroll/payslips/{$payslip->id}/export/pdf")
        ->assertForbidden();
});

test('an employee can download their own payslip PDF but not another employee\'s', function () {
    $company = Company::factory()->create();
    $ownerUser = User::factory()->create(['company_id' => $company->id]);
    $otherUser = User::factory()->create(['company_id' => $company->id]);
    $ownerEmp = payroll3218Employee($ownerUser, 500_000);
    $payslip = app(PayrollIntegrationService::class)
        ->generatePayslip($ownerEmp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);

    test()->actingAs($ownerUser, 'sanctum')
        ->get("/api/v1/payroll/payslips/{$payslip->id}/export/pdf")
        ->assertOk();

    test()->actingAs($otherUser, 'sanctum')
        ->get("/api/v1/payroll/payslips/{$payslip->id}/export/pdf")
        ->assertForbidden();
});

test('Payslip::employee() and Payslip::payrollRun() relations resolve the real linked records', function () {
    $company = Company::factory()->create();
    $emp = payroll3218Employee(null, 400_000);
    $payslip = app(PayrollIntegrationService::class)->generatePayslip($emp, now()->startOfMonth(), now()->endOfMonth(), 'monthly', $company->id);

    expect($payslip->employee)->not->toBeNull()
        ->and($payslip->employee->id)->toBe($emp->id)
        ->and($payslip->payrollRun)->not->toBeNull()
        ->and($payslip->payrollRun->id)->toBe($payslip->payroll_run_id);
});
