<?php

declare(strict_types=1);

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company as TenantCompany;
use App\Models\User;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\ExpenseReport;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\VatDeclaration;
use Tests\TestCase;

/**
 * Chantier 6: routes that either had real controller code but no route at
 * all (chart-of-accounts, bank-reconciliation, vat-declarations, invoices
 * show/create/edit, consolidations, ai-anomaly-detection), or a route that
 * existed against the wrong prop name (expenses).
 */
class AccountingScreensWebTest extends TestCase
{
    public function test_chart_of_accounts_renders()
    {
        $user = User::factory()->create();
        ChartOfAccount::factory()->count(3)->create();

        $response = $this->actingAs($user)->get('/accounting/chart-of-accounts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/ChartOfAccounts/Index', false)
            ->has('accounts')
            ->has('filters')
        );
    }

    public function test_expenses_renders_with_expenses_prop_not_reports()
    {
        $user = User::factory()->create();
        ExpenseReport::factory()->create();

        $response = $this->actingAs($user)->get('/accounting/expenses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Expenses/Index', false)
            ->has('expenses')
            ->has('stats')
        );
    }

    public function test_bank_reconciliation_index_renders()
    {
        $user = User::factory()->create();
        BankAccount::factory()->count(2)->create();

        $response = $this->actingAs($user)->get('/accounting/bank-reconciliation');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/BankReconciliation/Index', false)
            ->has('accounts')
        );
    }

    public function test_bank_reconciliation_reconcile_renders()
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create();

        $response = $this->actingAs($user)->get("/accounting/bank-reconciliation/{$account->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/BankReconciliation/Reconcile', false)
            ->where('account.id', $account->id)
            ->has('transactions')
            ->has('glEntries')
        );
    }

    public function test_vat_declarations_index_renders()
    {
        $user = User::factory()->create();
        VatDeclaration::factory()->count(2)->create();

        $response = $this->actingAs($user)->get('/accounting/vat-declarations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/VatDeclaration/Index', false)
            ->has('declarations')
        );
    }

    public function test_vat_declaration_show_renders()
    {
        $user = User::factory()->create();
        $declaration = VatDeclaration::factory()->create();

        $response = $this->actingAs($user)->get("/accounting/vat-declarations/{$declaration->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/VatDeclaration/Show', false)
            ->where('declaration.id', $declaration->id)
        );
    }

    public function test_invoice_show_renders_with_synthesized_customer()
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => 42, 'customer_name' => 'Client Test SARL']);

        $response = $this->actingAs($user)->get("/accounting/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Invoices/Show', false)
            ->where('invoice.id', $invoice->id)
            ->where('invoice.customer.id', 42)
            ->where('invoice.customer.name', 'Client Test SARL')
        );
    }

    public function test_invoice_create_renders_without_invoice_prop()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/accounting/invoices/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/Invoices/Form', false));
    }

    public function test_invoice_edit_renders_with_invoice_prop()
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($user)->get("/accounting/invoices/{$invoice->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Invoices/Form', false)
            ->where('invoice.id', $invoice->id)
        );
    }

    public function test_consolidations_index_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/accounting/consolidations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/Consolidation/Index', false));
    }

    public function test_consolidations_create_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/accounting/consolidations/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/Consolidation/Create', false));
    }

    public function test_consolidations_show_renders()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $response = $this->actingAs($user)->get("/accounting/consolidations/{$company->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/Consolidation/Detail', false));
    }

    public function test_consolidation_hierarchies_index_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/accounting/consolidation-hierarchies');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/ConsolidationHierarchies/Index', false));
    }

    public function test_ai_anomaly_detection_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/accounting/ai-anomaly-detection');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/AIAnomalyDetection/Index', false));
    }

    public function test_consolidation_hierarchy_create_derives_company_from_authenticated_user()
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'accounting.consolidation.create', 'guard_name' => 'web']);
        $tenantCompany = TenantCompany::factory()->create();
        $user = User::factory()->for($tenantCompany)->create();
        $user->givePermissionTo('accounting.consolidation.create');

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/consolidation-hierarchies', [
            'name' => 'Groupe Test',
            'type' => 'holding',
            'company_id' => $tenantCompany->id,
            'ownership_percentage' => 100,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('consolidation_hierarchies', [
            'name' => 'Groupe Test',
            'company_id' => $tenantCompany->id,
        ]);
    }

    public function test_expense_report_create_resolves_employee_from_authenticated_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/expense-reports', [
            'title' => 'Note de frais mai 2026',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('acc_expense_reports', [
            'title' => 'Note de frais mai 2026',
            'employee_id' => $user->id,
            'status' => 'draft',
        ]);
    }
}
