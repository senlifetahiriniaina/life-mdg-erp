<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Sales\Models\SalesOrder;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 32 — année d'exercice comptable, import de données de démarrage
 * formalisé, dettes fournisseurs, devise multi-facture Ventes, scan de
 * facture fournisseur (Claude vision + validation humaine).
 */
function chantier32User(string $role = 'accountant'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // Le plan comptable/journaux ne sont semés que par AccountingDatabaseSeeder,
    // pas par le seeder RBAC de base — même précédent déjà documenté pour
    // CostingSheetTest/Chantier22/26 dans ce dépôt.
    if (ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }
    if (\Modules\Shared\Models\Currency::count() === 0) {
        test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    }

    $company = Company::create([
        'name'     => 'Chantier32 Co '.uniqid(),
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');

    return $user;
}

/**
 * `DefaultDataSeeder::seedFiscalYears()` only ever seeds Exercice 2025/2026
 * for the one specific default company ("Ma Société", `Company::firstOrCreate`
 * by name) — an arbitrary ad-hoc `Company::create()` in a test never gets
 * them. This helper acts as a real user of that exact default company so the
 * seeded-fiscal-years assertions are meaningful.
 */
function chantier32DefaultCompanyUser(string $role = 'accountant'): User
{
    // Ensures RolesAndPermissionsSeeder/AccountingDatabaseSeeder/DefaultDataSeeder
    // (the latter is what actually creates "Ma Société" + seeds its fiscal years)
    // have run, via the same guarded seeding logic as chantier32User().
    chantier32User();

    $company = Company::where('name', 'Ma Société')->firstOrFail();

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');

    return $user;
}

function chantier32Bill(User $user, float $total, float $paid, string $dueDate): Invoice
{
    return Invoice::create([
        'number'       => 'BILL-'.uniqid(),
        'type'         => 'bill',
        'partner_type' => 'vendor',
        'partner_name' => 'Fournisseur '.uniqid(),
        'invoice_date' => now()->subDays(45),
        'due_date'     => $dueDate,
        'currency'     => 'MGA',
        'status'       => 'sent',
        'subtotal'     => $total,
        'tax_amount'   => 0,
        'total'        => $total,
        'amount_paid'  => $paid,
        'created_by'   => $user->id,
    ]);
}

describe('A1 — fiscal years, real CRUD + auto-link to journal entries', function () {
    test('2025 and 2026 are seeded, both Jan 1 to Dec 31', function () {
        $user = chantier32DefaultCompanyUser();

        $years = FiscalYear::forCompany($user->company_id)->orderBy('start_date')->get();

        expect($years)->toHaveCount(2);
        expect($years[0]->name)->toBe('Exercice 2025');
        expect($years[0]->start_date->format('Y-m-d'))->toBe('2025-01-01');
        expect($years[0]->end_date->format('Y-m-d'))->toBe('2025-12-31');
        expect($years[1]->name)->toBe('Exercice 2026');
        expect($years[1]->start_date->format('Y-m-d'))->toBe('2026-01-01');
        expect($years[1]->end_date->format('Y-m-d'))->toBe('2026-12-31');
    });

    test('create/close a fiscal year via the real HTTP route', function () {
        chantier32User();

        $store = test()->postJson('/api/v1/accounting/fiscal-years', [
            'name'       => 'Exercice 2027',
            'start_date' => '2027-01-01',
            'end_date'   => '2027-12-31',
        ]);
        $store->assertCreated();
        $id = $store->json('data.id');

        $close = test()->postJson("/api/v1/accounting/fiscal-years/{$id}/close");
        $close->assertOk();
        $close->assertJsonPath('data.is_closed', true);
        $close->assertJsonPath('data.status', 'closed');

        // Never deleted — a closed fiscal year must still exist.
        expect(FiscalYear::find($id))->not->toBeNull();
    });

    test('a journal entry posted with a date in 2026 is auto-linked to Exercice 2026', function () {
        $user = chantier32DefaultCompanyUser();
        $journal = Journal::first();

        $response = test()->postJson('/api/v1/accounting/journal-entries', [
            'journal_id'  => $journal->id,
            'date'        => '2026-05-10',
            'description' => 'Chantier32 test',
            'lines'       => [
                ['account_code' => '5211', 'debit' => 1000, 'credit' => 0],
                ['account_code' => '707', 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response->assertCreated();
        $fy2026 = FiscalYear::coveringDate('2026-05-10', $user->company_id);
        $response->assertJsonPath('data.fiscal_year_id', $fy2026->id);
    });

    test('a user from another company cannot view/close/delete a fiscal year', function () {
        $owner = chantier32DefaultCompanyUser();
        $fy = FiscalYear::forCompany($owner->company_id)->first();

        $intruder = User::factory()->create(['company_id' => Company::create(['name' => 'Other Co', 'currency' => 'MGA', 'timezone' => 'UTC'])->id]);
        $intruder->assignRole('accountant');
        test()->actingAs($intruder, 'sanctum');

        test()->getJson("/api/v1/accounting/fiscal-years/{$fy->id}")->assertForbidden();
        test()->postJson("/api/v1/accounting/fiscal-years/{$fy->id}/close")->assertForbidden();
        test()->deleteJson("/api/v1/accounting/fiscal-years/{$fy->id}")->assertForbidden();
    });

    test('a role outside the accounting gate is denied on the fiscal-years route group', function () {
        $user = chantier32User('sales-rep');

        test()->getJson('/api/v1/accounting/fiscal-years')->assertForbidden();
    });

    test('the web page renders the real Inertia component', function () {
        chantier32User();

        $response = test()->get('/accounting/fiscal-years');
        $response->assertOk();
        // `false` bypasses the on-disk existence check — the real component
        // lives at Modules/Accounting/resources/js/Pages/FiscalYears/Index.vue,
        // not the doubled-prefix path the Inertia testing page-finder's
        // literal `<module>/resources/js/Pages` + component-name concatenation
        // would look for (`.../Pages/Accounting/FiscalYears/Index.vue`) — same
        // precedent already established by Chantier26FinanceReviewTest.
        $response->assertInertia(fn ($page) => $page->component('Accounting/FiscalYears/Index', false));
    });
});

describe('A2 — startup data import commands, real Artisan execution', function () {
    test('accounting:import-treasury-history preview then --commit posts real balanced journal entries', function () {
        chantier32User();

        $path = storage_path('app/chantier32_treasury_test.csv');
        file_put_contents($path, "date,libelle,montant\n2026-04-01,Vente comptant test,25000\n");

        $preview = \Illuminate\Support\Facades\Artisan::call('accounting:import-treasury-history', [
            'file' => $path, '--treasury-account' => '5711',
        ]);
        expect($preview)->toBe(0);
        expect(\Illuminate\Support\Facades\Artisan::output())->toContain('Aperçu seul');

        $before = \Modules\Accounting\Models\JournalEntry::count();
        $commit = \Illuminate\Support\Facades\Artisan::call('accounting:import-treasury-history', [
            'file' => $path, '--treasury-account' => '5711', '--commit' => true,
        ]);
        expect($commit)->toBe(0);
        $after = \Modules\Accounting\Models\JournalEntry::count();
        expect($after)->toBe($before + 1);

        @unlink($path);
    });

    test('accounting:import-chart-of-accounts creates a real ChartOfAccount row', function () {
        chantier32User();

        $path = storage_path('app/chantier32_chart_test.csv');
        file_put_contents($path, "code,libelle,type\n8888,Compte import test,expense\n");

        expect(ChartOfAccount::where('code', '8888')->exists())->toBeFalse();

        $exit = \Illuminate\Support\Facades\Artisan::call('accounting:import-chart-of-accounts', ['file' => $path]);
        expect($exit)->toBe(0);

        $account = ChartOfAccount::where('code', '8888')->first();
        expect($account)->not->toBeNull();
        expect($account->name)->toBe('Compte import test');

        @unlink($path);
    });

    test('accounting:import-chart-of-accounts --dry-run writes nothing', function () {
        chantier32User();

        $path = storage_path('app/chantier32_chart_dryrun.csv');
        file_put_contents($path, "code,libelle,type\n8877,Compte dry run,expense\n");

        \Illuminate\Support\Facades\Artisan::call('accounting:import-chart-of-accounts', ['file' => $path, '--dry-run' => true]);

        expect(ChartOfAccount::where('code', '8877')->exists())->toBeFalse();

        @unlink($path);
    });
});

describe('A3 — supplier aged payables, computed live, never a pre-loaded amount', function () {
    test('an installation with no supplier bills reports an honest zero', function () {
        chantier32User();

        $response = test()->getJson('/api/v1/accounting/supplier-debt/aged-payables');
        $response->assertOk();
        $response->assertJsonPath('total_due', 0);
        expect($response->json('suppliers'))->toBe([]);
    });

    test('a real overdue unpaid bill lands in the correct aging bucket', function () {
        $user = chantier32User();
        chantier32Bill($user, 100000, 30000, now()->subDays(15)->toDateString());

        $response = test()->getJson('/api/v1/accounting/supplier-debt/aged-payables');
        $response->assertOk();
        $response->assertJsonPath('total_due', 70000);
        $response->assertJsonPath('suppliers.0.buckets.current', 70000);
        $response->assertJsonPath('suppliers.0.buckets.d31_60', 0);
        $response->assertJsonPath('suppliers.0.invoices.0.days_overdue', 15);
    });

    test('a 90+ day overdue bill lands in the d90_plus bucket', function () {
        $user = chantier32User();
        chantier32Bill($user, 50000, 0, now()->subDays(95)->toDateString());

        $response = test()->getJson('/api/v1/accounting/supplier-debt/aged-payables');
        $response->assertJsonPath('suppliers.0.buckets.d90_plus', 50000);
    });

    test('the web page renders the real Inertia component', function () {
        chantier32User();

        $response = test()->get('/accounting/supplier-debt');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/SupplierDebt/Index', false));
    });
});

describe('B — Sales multi-currency (MGA/EUR/USD/CNY) + FX-converted deposit journal entries', function () {
    test('SalesService::createOrder defaults to MGA, not XOF', function () {
        $user = chantier32User();

        $order = app(\Modules\Sales\Services\SalesService::class)->createOrder([
            'tenant_id'  => (string) $user->company_id,
            'created_by' => $user->id,
            'lines'      => [['description' => 'Article', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
        ]);

        expect($order->currency)->toBe('MGA');
    });

    test('an order can be created in each of MGA/EUR/USD/CNY via the real sales_orders write path', function ($currency) {
        $user = chantier32User();

        $order = SalesOrder::create([
            'tenant_id'  => $user->company_id,
            'reference'  => 'SO-CUR-'.$currency,
            'status'     => 'draft',
            'currency'   => $currency,
            'subtotal'   => 100,
            'total'      => 100,
            'created_by' => $user->id,
        ]);

        expect($order->fresh()->currency)->toBe($currency);
    })->with(['MGA', 'EUR', 'USD', 'CNY']);

    test('a deposit payment on a non-MGA order is FX-converted to MGA before posting, and the journal entry balances', function () {
        $user = chantier32User();

        $order = app(\Modules\Sales\Services\SalesService::class)->createOrder([
            'tenant_id'  => (string) $user->company_id,
            'created_by' => $user->id,
            'currency'   => 'EUR',
            'lines'      => [['description' => 'Article EUR', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 0]],
        ]);
        expect($order->total)->toEqualWithDelta(200.0, 0.01);

        $depositService = app(\Modules\Sales\Services\SalesDepositService::class);
        $depositService->requestDeposit($order, 50, $user->id);
        $order->refresh();

        $depositInvoice = Invoice::find($order->deposit_invoice_id);
        expect((float) $depositInvoice->total)->toEqualWithDelta(100.0, 0.01);

        $before = \Modules\Accounting\Models\JournalEntry::count();
        $depositService->recordDepositPayment($order, (float) $depositInvoice->total, 'bank_transfer', 'REF', $user->id);
        expect(\Modules\Accounting\Models\JournalEntry::count())->toBe($before + 1);

        $entry = \Modules\Accounting\Models\JournalEntry::latest('id')->first();
        expect($entry->currency)->toBe('MGA');

        $debit = (float) $entry->lines()->sum('debit');
        $credit = (float) $entry->lines()->sum('credit');
        expect($debit)->toEqualWithDelta($credit, 0.01);

        $eur = \Modules\Shared\Models\Currency::where('code', 'EUR')->first();
        $mga = \Modules\Shared\Models\Currency::where('code', 'MGA')->first();
        $expected = round((100.0 / (float) $eur->exchange_rate_to_usd) * (float) $mga->exchange_rate_to_usd, 2);
        expect($debit)->toEqualWithDelta($expected, 0.01);
        // Confirms the posted amount is NOT the raw un-converted 100 (the
        // pre-fix bug this test locks in).
        expect($debit)->not->toEqualWithDelta(100.0, 1.0);
    });

    test('InvoiceController::update() accepts a currency change', function () {
        $user = chantier32User();
        $invoice = Invoice::create([
            'number' => 'INV-CUR-1', 'type' => 'invoice', 'partner_type' => 'customer',
            'invoice_date' => now(), 'currency' => 'USD', 'status' => 'draft',
            'subtotal' => 0, 'tax_amount' => 0, 'total' => 0, 'created_by' => $user->id,
        ]);

        $response = test()->putJson("/api/v1/accounting/invoices/{$invoice->id}", ['currency' => 'CNY']);
        $response->assertOk();
        expect($invoice->fresh()->currency)->toBe('CNY');
    });
});

describe('C — supplier invoice scan (Claude vision + mandatory human review)', function () {
    function chantier32ScanFile(string $name = 'scan.png'): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $path = sys_get_temp_dir().'/'.uniqid().'_'.$name;
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    test('preview() real HTTP multipart upload returns enabled:false and empty fields when AI is not configured, never an error', function () {
        chantier32User();

        $response = test()->call('POST', '/api/v1/accounting/supplier-invoice-scan/preview', [], [], [
            'file' => chantier32ScanFile(),
        ]);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        expect($data['enabled'])->toBeFalse();
        expect($data['fields']['partner_name'])->toBeNull();
        expect($data['fields']['lines'])->toBe([]);
    });

    test('preview() rejects an unsupported file type', function () {
        chantier32User();

        $path = sys_get_temp_dir().'/'.uniqid().'.txt';
        file_put_contents($path, 'not an invoice');
        $file = new UploadedFile($path, 'notes.txt', 'text/plain', null, true);

        $response = test()->call('POST', '/api/v1/accounting/supplier-invoice-scan/preview', [], [], ['file' => $file]);
        $response->assertStatus(422);
    });

    test('commit() creates a real bill/vendor Invoice with lines and stores the original file', function () {
        $user = chantier32User();

        $response = test()->call('POST', '/api/v1/accounting/supplier-invoice-scan/commit', [
            'partner_name'  => 'Fournisseur Scan Test',
            'invoice_date'  => '2026-08-01',
            'due_date'      => '2026-09-01',
            'currency'      => 'MGA',
            'lines'         => [
                ['description' => 'Tissu importé', 'quantity' => 10, 'unit_price' => 5000, 'tax_rate' => 0],
            ],
        ], [], ['file' => chantier32ScanFile()]);

        $response->assertCreated();
        $data = json_decode($response->getContent(), true);

        expect($data['data']['type'])->toBe('bill');
        expect($data['data']['partner_type'])->toBe('vendor');
        expect((float) $data['data']['total'])->toBe(50000.0);
        expect($data['data']['line_items'])->toHaveCount(1);
        expect($data['data']['scan_path'])->not->toBeNull();

        expect(Storage::disk('local')->exists($data['data']['scan_path']))->toBeTrue();

        $invoice = Invoice::find($data['data']['id']);
        expect($invoice->created_by)->toBe($user->id);
    });

    test('commit() without an uploaded file still creates the invoice (manual entry after a failed/skipped scan)', function () {
        chantier32User();

        $response = test()->postJson('/api/v1/accounting/supplier-invoice-scan/commit', [
            'partner_name' => 'Fournisseur Manuel',
            'invoice_date' => '2026-08-01',
            'currency'     => 'MGA',
            'lines'        => [['description' => 'Ligne manuelle', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.scan_path', null);
    });

    test('commit() requires at least one line and a partner name', function () {
        chantier32User();

        $response = test()->postJson('/api/v1/accounting/supplier-invoice-scan/commit', [
            'invoice_date' => '2026-08-01',
            'currency'     => 'MGA',
            'lines'        => [],
        ]);

        $response->assertStatus(422);
    });

    test('a role outside the accounting gate is denied on the scan routes', function () {
        chantier32User('sales-rep');

        test()->postJson('/api/v1/accounting/supplier-invoice-scan/commit', [
            'partner_name' => 'X', 'invoice_date' => '2026-01-01', 'currency' => 'MGA',
            'lines' => [['unit_price' => 10]],
        ])->assertForbidden();
    });

    test('the web page renders the real Inertia component', function () {
        chantier32User();

        $response = test()->get('/accounting/supplier-invoice-scan');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/SupplierInvoiceScan/Index'));
    });
});

describe('AI Assist — new Chantier 32 fallback guidance', function () {
    test('the 3 new actions return non-empty fr+en fallback guidance via the real HTTP endpoint', function () {
        chantier32User();

        foreach (['scan_supplier_invoice', 'view_aged_payables', 'manage_fiscal_years'] as $action) {
            foreach (['fr', 'en'] as $locale) {
                $response = test()->postJson('/api/v1/ai/assist', [
                    'module' => 'Accounting', 'action' => $action, 'locale' => $locale,
                ]);
                $response->assertOk();
                expect($response->json('enabled'))->toBeFalse();
                expect($response->json('what_to_do'))->not->toBeEmpty();
            }
        }
    });
});
