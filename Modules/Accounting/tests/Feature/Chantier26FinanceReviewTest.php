<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FinanceReview;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Sales\Models\SalesObjective;
use Modules\Sales\Models\SalesOrder;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 26 (volet D) — revue finance mensuelle/trimestrielle : réalisation
 * (calculée en direct, jamais stockée) des objectifs commerciaux validés
 * (volet B) et du budget (volet C) sur une période donnée.
 */
function financeReviewUser(string $role = 'finance-manager'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // Le plan comptable/journaux ne sont semés que par AccountingDatabaseSeeder,
    // même précédent déjà documenté pour Chantier22/26 volets A/C.
    if (ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name'     => 'Chantier26 FinanceReview Co '.uniqid(),
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');

    return $user;
}

function postFrLedgerEntry(int $companyIgnored, string $accountCode, float $debit, float $credit, \Carbon\Carbon $date, string $ref): void
{
    $account = ChartOfAccount::where('code', $accountCode)->firstOrFail();
    $bank    = ChartOfAccount::where('code', '512')->firstOrFail();
    $journal = Journal::first();

    $entry = JournalEntry::create([
        'journal_id'  => $journal->id,
        'entry_date'  => $date,
        'reference'   => $ref,
        'description' => 'test',
    ]);

    JournalEntryLine::create(['entry_id' => $entry->id, 'account_id' => $account->id, 'debit' => $debit, 'credit' => $credit]);
    JournalEntryLine::create(['entry_id' => $entry->id, 'account_id' => $bank->id, 'debit' => $credit, 'credit' => $debit]);
}

describe('FinanceReviewService — objective realization computed live', function () {
    test('a validated global objective is compared to real confirmed sales within the period', function () {
        $user = financeReviewUser();

        SalesObjective::create([
            'tenant_id'     => $user->company_id,
            'scope'         => 'global',
            'period_start'  => '2026-01-01',
            'period_end'    => '2026-01-31',
            'target_amount' => 300000,
            'currency'      => 'MGA',
            'status'        => 'validated',
            'source'        => 'manual',
        ]);

        SalesOrder::create([
            'tenant_id'    => $user->company_id,
            'reference'    => 'SO-FR-1',
            'status'       => 'confirmed',
            'currency'     => 'MGA',
            'subtotal'     => 150000,
            'total'        => 150000,
            'confirmed_at' => \Carbon\Carbon::create(2026, 1, 15),
            'created_by'   => $user->id,
        ]);

        $response = $this->getJson('/api/v1/accounting/finance-reviews/realization?'.http_build_query([
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
        ]));

        $response->assertOk();
        $objectives = $response->json('objectives');
        expect($objectives)->toHaveCount(1)
            ->and((float) $objectives[0]['prorated_target'])->toBe(300000.0)
            ->and((float) $objectives[0]['actual'])->toBe(150000.0)
            ->and((float) $objectives[0]['realization_percent'])->toBe(50.0);
    });

    test('an objective outside the review period does not appear', function () {
        $user = financeReviewUser();

        SalesObjective::create([
            'tenant_id'     => $user->company_id,
            'scope'         => 'global',
            'period_start'  => '2026-03-01',
            'period_end'    => '2026-03-31',
            'target_amount' => 100000,
            'currency'      => 'MGA',
            'status'        => 'validated',
            'source'        => 'manual',
        ]);

        $response = $this->getJson('/api/v1/accounting/finance-reviews/realization?'.http_build_query([
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
        ]));

        $response->assertOk();
        expect($response->json('objectives'))->toHaveCount(0);
    });

    test('a proposed (not validated) objective is excluded', function () {
        $user = financeReviewUser();

        SalesObjective::create([
            'tenant_id'     => $user->company_id,
            'scope'         => 'global',
            'period_start'  => '2026-01-01',
            'period_end'    => '2026-01-31',
            'target_amount' => 100000,
            'currency'      => 'MGA',
            'status'        => 'proposed',
            'source'        => 'ai',
        ]);

        $response = $this->getJson('/api/v1/accounting/finance-reviews/realization?'.http_build_query([
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
        ]));

        $response->assertOk();
        expect($response->json('objectives'))->toHaveCount(0);
    });
});

describe('FinanceReviewService — budget realization reads the real ledger, never actual_amount', function () {
    test('a budget line is compared to the real journal movement on its account, not the never-populated actual_amount column', function () {
        $user = financeReviewUser();

        $budget = Budget::create([
            'company_id'  => $user->company_id,
            'name'        => 'Test budget',
            'fiscal_year' => 2026,
            'status'      => 'draft',
        ]);

        $account707 = ChartOfAccount::where('code', '707')->firstOrFail();
        BudgetLine::create([
            'budget_id'       => $budget->id,
            'account_id'      => $account707->id,
            // actual_amount deliberately left at its default 0 — the service
            // must never read it, only the real ledger.
            'budgeted_amount' => 100000,
            'category'        => '707',
            'period'          => '2026-01',
            'period_month'    => 1,
            'period_year'     => 2026,
        ]);

        postFrLedgerEntry($user->company_id, '707', 0, 120000, \Carbon\Carbon::create(2026, 1, 10), 'FR-1');

        $response = $this->getJson('/api/v1/accounting/finance-reviews/realization?'.http_build_query([
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
            'budget_id'    => $budget->id,
        ]));

        $response->assertOk();
        $lines = $response->json('budget_lines');
        expect($lines)->toHaveCount(1)
            ->and((float) $lines[0]['budgeted_amount'])->toBe(100000.0)
            ->and((float) $lines[0]['actual_amount'])->toBe(120000.0)
            ->and((float) $lines[0]['variance'])->toBe(20000.0)
            ->and((float) $lines[0]['realization_percent'])->toBe(120.0);
    });

    test('a budget from a different company cannot be requested (cross-tenant denial)', function () {
        $otherUser   = financeReviewUser();
        $otherBudget = Budget::create([
            'company_id'  => $otherUser->company_id,
            'name'        => 'Other Co budget',
            'fiscal_year' => 2026,
            'status'      => 'draft',
        ]);

        financeReviewUser();

        $this->getJson('/api/v1/accounting/finance-reviews/realization?'.http_build_query([
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
            'budget_id'    => $otherBudget->id,
        ]))->assertStatus(404);
    });
});

describe('API — finance review journal CRUD', function () {
    test('a review is created and later listed', function () {
        $user = financeReviewUser();

        $response = $this->postJson('/api/v1/accounting/finance-reviews', [
            'cadence'      => 'monthly',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
            'comments'     => 'RAS, réalisation conforme.',
        ]);

        $response->assertCreated();
        expect(FinanceReview::count())->toBe(1)
            ->and(FinanceReview::first()->reviewer_id)->toBe($user->id);

        $list = $this->getJson('/api/v1/accounting/finance-reviews');
        $list->assertOk();
        expect($list->json('data'))->toHaveCount(1);
    });

    test('a role with no accounting.financereview.create permission is denied', function () {
        if (Permission::count() === 0) {
            test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $company = Company::create(['name' => 'NoPerm FR Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $user    = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('warehouse-operator');
        test()->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/accounting/finance-reviews', [
            'cadence'      => 'monthly',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
        ])->assertStatus(403);
    });

    test('web page is reachable and renders the real Inertia component', function () {
        financeReviewUser();

        $response = $this->get('/accounting/finance-review');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/FinanceReview/Index', false));
    });
});
