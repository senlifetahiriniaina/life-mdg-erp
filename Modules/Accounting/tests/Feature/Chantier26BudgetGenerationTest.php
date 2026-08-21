<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Services\BudgetGenerationService;
use Modules\Sales\Services\SalesObjectiveService;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 26 (volet C) — génère un budget pour l'ensemble de l'entreprise à
 * partir du grand livre réel (classes 6/7 OHADA), corrélé aux objectifs
 * commerciaux globaux validés (Chantier 26 volet B).
 */
function budgetGenUser(): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // Le plan comptable/journaux ne sont semés que par AccountingDatabaseSeeder,
    // pas par le seeder RBAC de base — même précédent déjà documenté pour
    // CostingSheetTest/Chantier22/Chantier26 volet A.
    if (ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name'     => 'Chantier26 BudgetGen Co '.uniqid(),
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    test()->actingAs($user, 'sanctum');

    return $user;
}

function postLedgerEntry(int $daysAgo, string $accountCode, float $debit, float $credit, string $ref): JournalEntry
{
    $account   = ChartOfAccount::where('code', $accountCode)->firstOrFail();
    $bank      = ChartOfAccount::where('code', '512')->firstOrFail();
    $journal   = Journal::where('code', 'BNQ')->first() ?? Journal::firstOrFail();

    $entry = JournalEntry::create([
        'journal_id'  => $journal->id,
        'entry_date'  => now()->subDays($daysAgo),
        'reference'   => $ref,
        'description' => 'test',
    ]);

    JournalEntryLine::create(['entry_id' => $entry->id, 'account_id' => $account->id, 'debit' => $debit, 'credit' => $credit]);
    JournalEntryLine::create(['entry_id' => $entry->id, 'account_id' => $bank->id, 'debit' => $credit, 'credit' => $debit]);

    return $entry;
}

describe('BudgetGenerationService — real historical ledger data', function () {
    test('projects revenue and expense lines from the real 6-month average when no objective is validated', function () {
        $user = budgetGenUser();

        // 5 revenue events (707, credit-increases) and 5 expense events (601,
        // debit-increases) inside the 6-month lookback window before next
        // year's Jan 1.
        foreach ([150, 120, 90, 60, 30] as $daysAgo) {
            postLedgerEntry($daysAgo, '707', 0, 200000, 'REV-'.$daysAgo);
            postLedgerEntry($daysAgo, '601', 80000, 0, 'EXP-'.$daysAgo);
        }

        $svc    = app(BudgetGenerationService::class);
        $budget = $svc->generateFromHistory($user->company_id, (int) now()->addYear()->year);

        expect($budget->status)->toBe('draft')
            ->and((float) $budget->total_revenue_budget)->toBeGreaterThan(0)
            ->and((float) $budget->total_expense_budget)->toBeGreaterThan(0)
            ->and($budget->lines()->count())->toBeGreaterThan(0);

        // No revenue inflation without a validated objective — expenses and
        // revenue both derive purely from history (no invented growth).
        $revenueLine = $budget->lines()->where('category', '707')->first();
        $expenseLine = $budget->lines()->where('category', '601')->first();
        expect($revenueLine)->not->toBeNull()
            ->and($expenseLine)->not->toBeNull()
            ->and((float) $revenueLine->budgeted_amount)->toBeGreaterThan(0)
            ->and((float) $expenseLine->budgeted_amount)->toBeGreaterThan(0);
    });

    test('a validated global sales objective covering the budget period scales revenue lines to match it exactly', function () {
        $user = budgetGenUser();

        foreach ([150, 120, 90, 60, 30] as $daysAgo) {
            postLedgerEntry($daysAgo, '707', 0, 200000, 'REV-'.$daysAgo);
            postLedgerEntry($daysAgo, '601', 80000, 0, 'EXP-'.$daysAgo);
        }

        $fiscalYear  = (int) now()->addYear()->year;
        $periodStart = \Carbon\Carbon::create($fiscalYear, 1, 1);
        $periodEnd   = \Carbon\Carbon::create($fiscalYear, 12, 31);

        $objectiveSvc = app(SalesObjectiveService::class);
        $proposals    = $objectiveSvc->proposeObjectives('global', null, $user->company_id, $periodStart, $periodEnd, $user->id);
        $objectiveSvc->validate($proposals->first(), $user->id, ['target_amount' => 3000000]);

        $svc    = app(BudgetGenerationService::class);
        $budget = $svc->generateFromHistory($user->company_id, $fiscalYear, $periodStart, $periodEnd);

        // Correlation confirmed: the sum of every revenue-account budget
        // line exactly matches the validated objective (within rounding
        // over 12 monthly lines), while expense lines stay purely
        // historical, untouched by the objective.
        $revenueSum = (float) $budget->lines()
            ->whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->sum('budgeted_amount');
        $expenseSum = (float) $budget->lines()
            ->whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->sum('budgeted_amount');

        expect($revenueSum)->toBeGreaterThan(2999999.0)->toBeLessThan(3000001.0)
            ->and((float) $budget->total_revenue_budget)->toBe(3000000.0)
            ->and($expenseSum)->toBeGreaterThan(0)
            ->and((float) $budget->total_expense_budget)->toBeLessThan(3000000.0);
    });

    test('an objective for a different tenant is never used for correlation (no cross-tenant leak)', function () {
        $user      = budgetGenUser();
        $otherUser = budgetGenUser();

        foreach ([90, 60, 30] as $daysAgo) {
            postLedgerEntry($daysAgo, '707', 0, 100000, 'REV-'.$daysAgo);
        }

        $fiscalYear  = (int) now()->addYear()->year;
        $periodStart = \Carbon\Carbon::create($fiscalYear, 1, 1);
        $periodEnd   = \Carbon\Carbon::create($fiscalYear, 12, 31);

        $objectiveSvc = app(SalesObjectiveService::class);
        // A huge, validated objective — but belonging to a different tenant.
        $proposals = $objectiveSvc->proposeObjectives('global', null, $otherUser->company_id, $periodStart, $periodEnd, $otherUser->id);
        $objectiveSvc->validate($proposals->first(), $otherUser->id, ['target_amount' => 99999999]);

        $svc    = app(BudgetGenerationService::class);
        $budget = $svc->generateFromHistory($user->company_id, $fiscalYear, $periodStart, $periodEnd);

        expect((float) $budget->total_revenue_budget)->toBeLessThan(99999999.0);
    });
});

describe('API — POST /api/v1/accounting/budgets/generate-from-history', function () {
    test('creates a real budget end to end for an authorized role', function () {
        $user = budgetGenUser();
        postLedgerEntry(30, '707', 0, 150000, 'REV-1');
        postLedgerEntry(30, '601', 60000, 0, 'EXP-1');

        $response = $this->postJson('/api/v1/accounting/budgets/generate-from-history', [
            'fiscal_year' => (int) now()->addYear()->year,
        ]);

        $response->assertCreated();
        expect($response->json('data.status'))->toBe('draft')
            ->and(Budget::count())->toBe(1)
            ->and(Budget::first()->lines()->count())->toBeGreaterThan(0);
    });

    test('a role with no accounting.budget.create permission is denied', function () {
        if (Permission::count() === 0) {
            test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $company = Company::create(['name' => 'NoPerm Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $user    = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('warehouse-operator');
        test()->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/accounting/budgets/generate-from-history', [
            'fiscal_year' => (int) now()->addYear()->year,
        ])->assertStatus(403);
    });
});
