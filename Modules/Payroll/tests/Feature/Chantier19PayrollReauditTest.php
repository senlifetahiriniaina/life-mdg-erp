<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollIntegrationService;
use Modules\Payroll\Services\PayrollService;

/**
 * Chantier 19 Lot 2 (Payroll re-verification): a re-audit of Modules/Payroll
 * driven by real HTTP requests / php artisan tinker against realistic seeded
 * data, not just re-reading already-fixed code — the same "code reading alone
 * cannot detect a broken query/table/write-path" methodology that found 3
 * stacked bugs in Accounting/Reporting during Chantier 18 despite 3 prior
 * code-reading-only audits (see CLAUDE.md). Confirmed the Chantier 8.3/10
 * fixes (real EmployeeCompensation-based salary, company_id tenant scoping,
 * PayrollPolicy ID-space fix) are all still correct, and found 3 new,
 * previously-undetected real bugs, all fixed below:
 *
 * 1. Negative net salary for a zero-gross-salary employee — an employee with
 *    no EmployeeCompensation record correctly resolves gross_salary to 0
 *    (Chantier83PayrollZeroSalaryTest already locks that in), but
 *    calculateProgressiveIncomeTax() still applied a country's flat
 *    fixed_tax (SN's TRIMF=300) or minimum_tax floor (MG's IRSA=3000)
 *    UNCONDITIONALLY, producing a negative net_salary payslip. Confirmed
 *    empirically via tinker: calculateIncomeTax(0, 'SN') === 300.0 before
 *    the fix. The pre-existing zero-salary test never asserted net_salary,
 *    only gross_salary, so this went undetected.
 *
 * 2. generatePayslip()'s idempotency check ignored tenant_id entirely
 *    (Employee has no company/tenant-scoping column of its own anywhere —
 *    confirmed via Schema::hasColumn, out of this module's scope to fix),
 *    so once ANY tenant generated a payslip for an employee+period, every
 *    OTHER tenant's later generate call for that same employee+period
 *    silently returned the FIRST tenant's payslip instead of creating its
 *    own — permanently blocking that tenant from ever generating a
 *    correctly-tenant-tagged payslip for that employee/period. Fixed by
 *    scoping the idempotency lookup by tenant_id, matching PayrollRun's
 *    own ['tenant_id','period'] uniqueness. This does NOT fully close the
 *    underlying gap (Employee still has zero real per-company ownership,
 *    so "generate for tenant A" still pulls in every active employee
 *    system-wide) — that root cause lives in Modules\HR\Models\Employee
 *    and is out of this module's scope; documented in the chantier report.
 *
 * 3. postPayslipsToAccounting() never wrote to the real ledger at all —
 *    it created two bare JournalEntry HEADER rows per payslip using the
 *    deprecated flat entry_type/amount fields, zero JournalEntryLine rows,
 *    no account_id link — invisible to every real financial statement
 *    (OhadaReportService/FinancialReportService, both fixed in Chantier 18
 *    to read acc_journal_entry_lines). Rewritten onto the real header+
 *    lines scheme with real seeded account codes (641/421/447).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function payroll19User(Company $company, string $role): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function payroll19Employee(?User $user = null, ?float $baseSalary = 500_000): Employee
{
    $employee = Employee::factory()->create([
        'user_id'          => $user?->id,
        'status'           => 'active',
        'termination_date' => null,
    ]);

    if ($baseSalary !== null) {
        EmployeeCompensation::factory()->create([
            'employee_id'    => $employee->id,
            'base_salary'    => $baseSalary,
            'currency'       => 'MGA',
            'effective_date' => now()->startOfMonth()->subMonth(),
            'end_date'       => null,
        ]);
    }

    return $employee;
}

test('a real payslip generated for an employee with no compensation record has a non-negative net salary', function () {
    // Reproduces the exact scenario confirmed empirically via tinker: an
    // employee with zero real salary data still had a country's flat
    // fixed_tax/minimum_tax applied unconditionally, producing a negative
    // net_salary. This locks the fix in over the real generatePayslip()
    // path, not just calculateIncomeTax() in isolation.
    $employee = payroll19Employee(baseSalary: null);

    $service = app(PayrollIntegrationService::class);
    $payslip = $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth());

    expect($payslip)->not->toBeNull();
    expect((float) $payslip->gross_salary)->toBe(0.0);
    expect((float) $payslip->net_salary)->toBeGreaterThanOrEqual(0.0);
    expect((float) $payslip->total_deductions)->toBe(0.0);
});

test('calculateIncomeTax returns 0 for 0 gross salary across the real StatutorySchemes countries with a fixed/minimum tax', function () {
    $service = app(PayrollIntegrationService::class);

    // SN has a real fixed_tax (TRIMF=300); MG has a real minimum_tax floor
    // (IRSA=3000) — both were previously applied even at 0 gross salary.
    expect($service->calculateIncomeTax(0.0, 'SN'))->toBe(0.0);
    expect($service->calculateIncomeTax(0.0, 'MG'))->toBe(0.0);

    // Real bracket calculation still works correctly for actual income —
    // this guard must not silently zero out genuine tax liability.
    expect($service->calculateIncomeTax(340_000, 'MG'))->toBe(3_000.0);
});

test('generatePayslips scopes idempotency per tenant, not globally, over the real HTTP generate endpoint', function () {
    // Modules\HR\Models\Employee has no company/tenant-scoping column of
    // its own anywhere (confirmed via Schema::hasColumn during this
    // chantier's investigation) — generatePayslips() therefore pulls in
    // every active employee system-wide regardless of caller, a documented,
    // out-of-Payroll's-scope gap. This test locks in the narrower, real,
    // in-scope fix: once company A generates a payslip for a shared
    // employee, company B's own later generate call must still produce
    // its OWN tenant-B-tagged payslip for that same employee/period,
    // rather than silently reusing/blocking on company A's.
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = payroll19User($companyA, 'payroll-officer');
    $userB = payroll19User($companyB, 'payroll-officer');

    $sharedEmployee = payroll19Employee(baseSalary: 400_000);

    $period = now()->format('Y-m');

    $responseA = test()->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/payroll/generate', ['period' => $period]);
    $responseA->assertStatus(201);

    $responseB = test()->actingAs($userB, 'sanctum')
        ->postJson('/api/v1/payroll/generate', ['period' => $period]);
    $responseB->assertStatus(201);

    $periodDate = now()->startOfMonth()->toDateString();

    $hasA = Payslip::where('tenant_id', $companyA->id)
        ->where('employee_id', $sharedEmployee->id)
        ->whereDate('period', $periodDate)
        ->exists();
    $hasB = Payslip::where('tenant_id', $companyB->id)
        ->where('employee_id', $sharedEmployee->id)
        ->whereDate('period', $periodDate)
        ->exists();

    expect($hasA)->toBeTrue();
    expect($hasB)->toBeTrue();
});

test('postPayslipsToAccounting posts a real balanced journal entry with real account lines', function () {
    test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);

    $company = Company::factory()->create();
    $employee = payroll19Employee(baseSalary: 700_000);

    $service = app(PayrollIntegrationService::class);
    $payslip = $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth(), tenantId: $company->id);
    $payslip->update(['status' => 'approved']);

    $result = $service->postPayslipsToAccounting([$payslip->id]);

    expect($result['posted_count'])->toBe(1);

    $entry = JournalEntry::where('reference_type', 'Payslip')->where('reference_id', $payslip->id)->first();
    expect($entry)->not->toBeNull();

    $lines = $entry->lines()->get();
    expect($lines->count())->toBeGreaterThanOrEqual(2);

    $totalDebit = (float) $lines->sum('debit');
    $totalCredit = (float) $lines->sum('credit');
    expect(round($totalDebit, 2))->toBe(round($totalCredit, 2));
    expect(round($totalDebit, 2))->toBe(round((float) $payslip->gross_salary, 2));

    // Real seeded chart-of-account codes, not a phantom/never-seeded one.
    $accountCodes = $lines->map(fn ($l) => ChartOfAccount::find($l->account_id)?->code)->filter()->values();
    expect($accountCodes)->toContain('641'); // Rémunérations du personnel
    expect($accountCodes)->toContain('421'); // Personnel — Rémunérations dues
});

test('an employee can view their own payslip via the real self-service endpoint but not another employee\'s', function () {
    $company = Company::factory()->create();
    $userA = payroll19User($company, 'employee');
    $userB = payroll19User($company, 'employee');

    $empA = Employee::factory()->create(['user_id' => $userA->id, 'status' => 'active']);
    $empB = Employee::factory()->create(['user_id' => $userB->id, 'status' => 'active']);

    $payslipA = Payslip::factory()->create([
        'tenant_id'   => $company->id,
        'employee_id' => $empA->id,
        'status'      => 'paid',
    ]);
    $payslipB = Payslip::factory()->create([
        'tenant_id'   => $company->id,
        'employee_id' => $empB->id,
        'status'      => 'paid',
    ]);

    // A can list their own payslips and see only their own.
    $listResponse = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/payroll/me/payslips');
    $listResponse->assertOk();
    $ids = collect($listResponse->json('payslips.data'))->pluck('id');
    expect($ids)->toContain($payslipA->id);
    expect($ids)->not->toContain($payslipB->id);

    // A can view their own single payslip.
    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/payroll/payslips/{$payslipA->id}")
        ->assertOk();

    // A cannot view B's payslip by id (PayrollPolicy::view() ID-space fix).
    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/payroll/payslips/{$payslipB->id}")
        ->assertStatus(403);
});

test('a role with no payroll permissions is denied at the payroll route group', function () {
    $company = Company::factory()->create();
    $user = payroll19User($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/payroll/payslips')
        ->assertStatus(403);
});

test('taxes by country includes a real country_name, matching what Dashboard/Index.vue actually renders', function () {
    // Chantier 19 Lot 2: taxesByCountry() only ever returned country_code —
    // Dashboard/Index.vue's Taxes tab renders `country.country_name`, which
    // was always undefined/blank against the real API response. Fixed to
    // reuse StatutorySchemes' own real country names.
    $company = Company::factory()->create();
    $user = payroll19User($company, 'payroll-officer');
    $employee = payroll19Employee(baseSalary: 300_000);
    $employee->update(['nationality' => 'SN']);

    $service = app(PayrollIntegrationService::class);
    $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth(), tenantId: $company->id);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/payroll/taxes/by-country?period='.now()->format('Y-m').'&country=SN');

    $response->assertOk();
    $taxes = collect($response->json('taxes'));
    $sn = $taxes->firstWhere('country_code', 'SN');
    expect($sn)->not->toBeNull();
    expect($sn['country_name'])->toBe('Sénégal');
});

test('the payslips list endpoint returns a real Laravel paginator shape under the payslips key, matching what the dashboard reads', function () {
    // Chantier 19 Lot 2: Dashboard/Index.vue used to read
    // response.data.payslips directly as a flat array, but the controller
    // wraps a real paginator ({data:[...], current_page, ...}) under that
    // key — confirmed by inspecting the real response shape. The frontend
    // fix reads response.data.payslips.data; this test locks in the
    // backend contract that fix depends on so a future change to this
    // endpoint's shape is caught here, not silently in the browser.
    $company = Company::factory()->create();
    $user = payroll19User($company, 'payroll-officer');
    $employee = payroll19Employee(baseSalary: 300_000);
    $service = app(PayrollIntegrationService::class);
    $service->generatePayslip($employee, now()->startOfMonth(), now()->endOfMonth(), tenantId: $company->id);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/payroll/payslips?period='.now()->format('Y-m'));

    $response->assertOk();
    expect($response->json('payslips'))->toHaveKeys(['data', 'current_page', 'per_page', 'total']);
    expect($response->json('payslips.data'))->toBeArray();
});

test('payroll dashboard web route requires a real payroll-staff role, not just any authenticated user', function () {
    $company = Company::factory()->create();
    $user = payroll19User($company, 'sales-rep');

    test()->actingAs($user)->get('/payroll')->assertForbidden();

    $staff = payroll19User($company, 'hr-manager');
    test()->actingAs($staff)->get('/payroll')->assertOk();
});

test('PayrollService::createRun is idempotent per tenant+period, not dead code — confirmed a real live consumer via Workflow\'s payroll.generate_run automation action', function () {
    // Chantier 19 Lot 2: CLAUDE.md's Chantier 10 note documents this whole
    // class as having zero consumers anywhere — a repo-wide grep found that
    // stale: Modules\Workflow\Services\Actions\Phase52ActionHandler::
    // generatePayrollRun() (behind the real, registered 'payroll.generate_run'
    // Workflow node type) calls PayrollService::createRun() for real. Its
    // plain PayrollRun::create() had no idempotency check at all, unlike
    // PayrollIntegrationService's own PayrollRun-creation path — a second
    // automation trigger for the same tenant+period would throw a unique-
    // constraint violation. Also locks in the 'Y-m' vs stored 'Y-m-d'
    // period-format normalization the fix needed (the caller genuinely
    // passes a bare 'Y-m' string).
    $company = Company::factory()->create();
    $service = app(PayrollService::class);

    $run1 = $service->createRun($company->id, '2026-05');
    $run2 = $service->createRun($company->id, '2026-05');

    expect($run1->id)->toBe($run2->id);
    expect(PayrollRun::where('tenant_id', $company->id)->count())->toBe(1);
    expect($run1->period->toDateString())->toBe('2026-05-01');
});

test('payroll ai assist passes the caller\'s real Spatie role, not the phantom users.role column', function () {
    $company = Company::factory()->create();
    $user = payroll19User($company, 'payroll-officer');

    $captured = null;
    test()->mock(\Modules\AI\Services\AiContextualAssistantService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('getGuidance')
            ->withArgs(function ($module, $action, $context, $locale, $userRole) use (&$captured) {
                $captured = $userRole;

                return true;
            })
            ->andReturn(['enabled' => false, 'what_to_do' => '', 'how_to_do' => [], 'decision_indicators' => [], 'warnings' => [], 'next_actions' => [], 'tips' => []]);
    });

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/payroll/ai/assist', ['action' => 'run_payroll'])
        ->assertOk();

    expect($captured)->toBe('payroll-officer');
});
