<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\RecurringOrderTemplate;
use Modules\Sales\Models\SalesObjective;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\RecurringOrderService;
use Modules\Sales\Services\SalesService;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 32.16 — audit approfondi en 14 couches de Modules\Sales. Verrouille
 * chaque bug réel trouvé par exécution empirique (jamais une simple relecture
 * de code) — voir CLAUDE.md pour le détail des 14 couches et la synthèse
 * complète de ce chantier.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function salesDeepAuditUser(string $suffix, string $role = 'sales-manager'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name'     => "Chantier32.16 Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ─── Layer 9 — fake/dead: SalesOrderPolicy confirmed dead, deleted ───────────

test('SalesOrderPolicy has been removed — no Gate policy is registered for SalesOrder, and authorization still works fully via the controller\'s own checks', function () {
    expect(class_exists(\Modules\Sales\Policies\SalesOrderPolicy::class))->toBeFalse();
    expect(\Illuminate\Support\Facades\Gate::getPolicyFor(SalesOrder::class))->toBeNull();

    // The flat permission checks + forTenant() scoping the controller
    // already performs remain the sole, sufficient authorization layer —
    // re-confirmed end to end below and throughout this file.
    $user = salesDeepAuditUser('PolicyRemoved');
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/sales/orders')->assertOk();
});

// ─── Layer 6 — deep security: full re-verification of every SalesController
//     endpoint's tenant scoping + permission gate with 2 real companies ─────

test('every order endpoint is tenant-scoped and permission-gated for a company with zero sales permission', function () {
    $companyless = salesDeepAuditUser('NoPerm', 'warehouse-operator'); // no sales.* permission
    $owner       = salesDeepAuditUser('Owner');
    $order       = app(SalesService::class)->createOrder([
        'tenant_id'  => $owner->company_id,
        'currency'   => 'MGA',
        'created_by' => $owner->id,
        'lines'      => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $api = test()->actingAs($companyless, 'sanctum');

    $api->getJson('/api/v1/sales/orders')->assertForbidden();
    $api->postJson('/api/v1/sales/orders', ['lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]]])->assertForbidden();
    $api->getJson("/api/v1/sales/orders/{$order->id}")->assertForbidden();
    $api->putJson("/api/v1/sales/orders/{$order->id}", ['notes' => 'x'])->assertForbidden();
    $api->postJson("/api/v1/sales/orders/{$order->id}/confirm")->assertForbidden();
    $api->postJson("/api/v1/sales/orders/{$order->id}/cancel", ['reason' => 'x'])->assertForbidden();
    $api->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'confirmed'])->assertForbidden();
    $api->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])->assertForbidden();
    $api->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 1])->assertForbidden();
    $api->postJson("/api/v1/sales/orders/{$order->id}/balance/request")->assertForbidden();
    $api->postJson("/api/v1/sales/orders/{$order->id}/balance/pay", ['amount' => 1])->assertForbidden();
});

test('every quotation endpoint is tenant-scoped for a real cross-company id', function () {
    $userA  = salesDeepAuditUser('QuoteA');
    $userB  = salesDeepAuditUser('QuoteB');
    $quoteA = app(SalesService::class)->createQuotation([
        'tenant_id'  => $userA->company_id,
        'currency'   => 'MGA',
        'total'      => 50000,
        'created_by' => $userA->id,
    ]);

    $api = test()->actingAs($userB, 'sanctum');

    $api->getJson("/api/v1/sales/quotations/{$quoteA->id}")->assertNotFound();
    $api->putJson("/api/v1/sales/quotations/{$quoteA->id}", ['total' => 1])->assertNotFound();
    $api->postJson("/api/v1/sales/quotations/{$quoteA->id}/send")->assertNotFound();
    $api->postJson("/api/v1/sales/quotations/{$quoteA->id}/convert")->assertNotFound();

    // Untouched.
    $quoteA->refresh();
    expect($quoteA->status)->toBe('draft');
});

test('recurring order templates and sales objectives are also cross-tenant isolated', function () {
    $userA = salesDeepAuditUser('CrossA');
    $userB = salesDeepAuditUser('CrossB');

    $templateA = RecurringOrderTemplate::factory()->create(['tenant_id' => $userA->company_id, 'created_by' => $userA->id]);
    $templateA->lines()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'sequence' => 0]);

    $objectiveA = SalesObjective::create([
        'tenant_id'     => $userA->company_id,
        'scope'         => 'global',
        'period_start'  => now()->toDateString(),
        'period_end'    => now()->addMonth()->toDateString(),
        'target_amount' => 100000,
        'currency'      => 'MGA',
        'status'        => 'proposed',
        'source'        => 'manual',
    ]);

    $api = test()->actingAs($userB, 'sanctum');

    $api->getJson("/api/v1/sales/recurring-order-templates/{$templateA->id}")->assertNotFound();
    $api->postJson("/api/v1/sales/recurring-order-templates/{$templateA->id}/run")->assertNotFound();
    $api->deleteJson("/api/v1/sales/recurring-order-templates/{$templateA->id}")->assertNotFound();

    $api->putJson("/api/v1/sales/objectives/{$objectiveA->id}", ['target_amount' => 1])->assertNotFound();
    $api->postJson("/api/v1/sales/objectives/{$objectiveA->id}/validate")->assertNotFound();
    $api->deleteJson("/api/v1/sales/objectives/{$objectiveA->id}")->assertNotFound();

    // Untouched.
    expect($objectiveA->fresh()->status)->toBe('proposed');
});

// ─── Layer 8 — deposit/balance business validation re-verified end to end ───

test('deposit/balance cycle end to end produces 2 balanced journal entries, and paying an already-fully-paid deposit again is rejected', function () {
    $user  = salesDeepAuditUser('CycleFull');
    $order = app(SalesService::class)->createOrder([
        'tenant_id'  => $user->company_id,
        'currency'   => 'MGA',
        'created_by' => $user->id,
        'lines'      => [['description' => 'Pantalon EPI', 'quantity' => 100, 'unit_price' => 10000]],
    ]);
    $order->update(['status' => 'confirmed']);
    $api = test()->actingAs($user, 'sanctum');

    $api->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 40])->assertOk();
    $api->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 400000, 'method' => 'mvola'])
        ->assertOk()
        ->assertJsonPath('payment_stage', 'deposit_paid');

    // Re-verify: paying an already-fully-paid deposit invoice again is
    // rejected server-side (double-payment guard), not just a client-side
    // convenience — any positive amount now exceeds the invoice total.
    $api->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 1])->assertStatus(422);

    $api->postJson("/api/v1/sales/orders/{$order->id}/balance/request")->assertOk();
    $api->postJson("/api/v1/sales/orders/{$order->id}/balance/pay", ['amount' => 600000, 'method' => 'virement'])
        ->assertOk()
        ->assertJsonPath('payment_stage', 'paid_in_full');

    // Re-verify: paying an already-fully-paid balance again is rejected too.
    $api->postJson("/api/v1/sales/orders/{$order->id}/balance/pay", ['amount' => 1])->assertStatus(422);

    $entries = \Modules\Accounting\Models\JournalEntry::where('reference_type', SalesOrder::class)
        ->where('reference_id', $order->id)->with('lines.account')->get();
    expect($entries)->toHaveCount(2);
    foreach ($entries as $entry) {
        expect((float) $entry->lines->sum('debit'))->toBe((float) $entry->lines->sum('credit'));
    }
});

// ─── Layer 8 — quotation double-conversion race guard ────────────────────────

test('a quotation cannot be converted twice even when re-checked against a freshly-locked row', function () {
    $user  = salesDeepAuditUser('Convert');
    $quote = app(SalesService::class)->createQuotation([
        'tenant_id'  => $user->company_id,
        'currency'   => 'MGA',
        'total'      => 42000,
        'created_by' => $user->id,
    ]);

    $svc = app(SalesService::class);
    $order1 = $svc->convertQuotationToOrder($quote->fresh());

    expect(fn () => $svc->convertQuotationToOrder($quote->fresh()))
        ->toThrow(\RuntimeException::class);

    expect(SalesOrder::where('contact_id', $quote->contact_id)->count())->toBeGreaterThanOrEqual(1);
    expect($quote->fresh()->converted_to_order_id)->toBe($order1->id);
});

// ─── Layer 8 — recurring order double-invocation, re-verified ────────────────

test('generateDueOrders never double-processes a template across repeated invocations the same day', function () {
    $user = salesDeepAuditUser('Recurring');

    $template = RecurringOrderTemplate::factory()->create([
        'tenant_id'   => $user->company_id,
        'next_run_at' => now()->subDay()->toDateString(),
        'is_active'   => true,
        'created_by'  => $user->id,
    ]);
    $template->lines()->create(['description' => 'Ligne', 'quantity' => 10, 'unit_price' => 1000, 'sequence' => 0]);

    $svc = app(RecurringOrderService::class);

    $first  = $svc->generateDueOrders();
    $second = $svc->generateDueOrders(); // "double command invocation" scenario
    $third  = $svc->generateDueOrders();

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and($third)->toHaveCount(0);

    expect(SalesOrder::where('tenant_id', $user->company_id)->count())->toBe(1);
});

// ─── Layer 5/10 — factories were broken scaffold, now real ───────────────────

test('SalesOrder/SalesOrderLine/SalesQuotation factories produce real, schema-correct data', function () {
    $order = SalesOrder::factory()->create();
    expect($order->status)->toBeIn(['draft']);
    expect((float) $order->total)->toBeGreaterThan(0);
    expect($order->expected_delivery_date)->toBeNull();

    $line = \Modules\Sales\Models\SalesOrderLine::factory()->create();
    expect($line->sales_order_id)->toBeGreaterThan(0);
    expect((float) $line->line_total)->toBeGreaterThan(0);

    $quote = SalesQuotation::factory()->create();
    expect($quote->status)->toBe('draft');
    expect($quote->isConvertible())->toBeTrue();
});

// ─── Layer 5/10 — sales_quotations.account_id activated ──────────────────────

test('a quotation can now be tied to a CRM account, not just a contact, matching orders', function () {
    $user = salesDeepAuditUser('QuoteAccount');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/sales/quotations', [
        'account_id' => 77,
        'currency'   => 'MGA',
        'total'      => 15000,
    ])->assertCreated();

    expect($response->json('account_id'))->toBe(77);
    $this->assertDatabaseHas('sales_quotations', ['id' => $response->json('id'), 'account_id' => 77]);
});

// ─── Layer 8 — sales_rep_id must reference a real user ───────────────────────

test('storing an order with a non-existent sales_rep_id is rejected', function () {
    $user = salesDeepAuditUser('RepValidation');

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/sales/orders', [
        'currency'     => 'MGA',
        'sales_rep_id' => 999999,
        'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ])->assertStatus(422);
});

// ─── Layer 12 — API contract: real customer name / real total / real date ───

test('GET sales/orders returns a real contact/account payload SalesIndex.vue can actually render', function () {
    $user    = salesDeepAuditUser('Contract');
    $contact = \Modules\CRM\Models\Contact::factory()->create(['first_name' => 'Jean', 'last_name' => 'Rakoto']);

    app(SalesService::class)->createOrder([
        'tenant_id'  => $user->company_id,
        'contact_id' => $contact->id,
        'currency'   => 'MGA',
        'created_by' => $user->id,
        'lines'      => [['description' => 'x', 'quantity' => 2, 'unit_price' => 5000]],
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/sales/orders')->assertOk();
    $row = collect($response->json('data'))->first();

    expect($row['contact']['first_name'])->toBe('Jean')
        ->and($row['contact']['last_name'])->toBe('Rakoto')
        ->and((float) $row['total'])->toBe(10000.0)
        ->and($row['created_at'])->not->toBeNull();
});

// ─── Layer 12 — search filter, previously silently ignored server-side ──────

test('the search query param on GET sales/orders now actually filters by reference', function () {
    $user = salesDeepAuditUser('Search');
    $svc  = app(SalesService::class);

    $matching = $svc->createOrder([
        'tenant_id' => $user->company_id, 'currency' => 'MGA', 'created_by' => $user->id,
        'reference' => 'SO-FINDME-001',
        'lines'     => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);
    $svc->createOrder([
        'tenant_id' => $user->company_id, 'currency' => 'MGA', 'created_by' => $user->id,
        'reference' => 'SO-OTHER-002',
        'lines'     => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/sales/orders?search=FINDME')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(1)->and($ids->first())->toBe($matching->id);
});

// ─── Layer 14 — N+1 regression guard on the orders listing endpoint ─────────

test('listing orders with deposit/balance invoices does not scale query count with the number of orders (N+1 regression guard)', function () {
    // Chantier 32.16: tested directly against the same Eloquent query the
    // controller builds, bypassing HTTP/middleware-layer query noise
    // (auth/session/permission-cache queries whose count is not stable
    // across a warm vs cold cache and would make an HTTP-level query-count
    // assertion flaky for reasons unrelated to the N+1 fix itself — this
    // was confirmed empirically before settling on this approach).
    $user = salesDeepAuditUser('N1Guard');
    $svc  = app(SalesService::class);
    $dep  = app(\Modules\Sales\Services\SalesDepositService::class);

    $buildOrders = function (int $count) use ($svc, $dep, $user) {
        for ($i = 0; $i < $count; $i++) {
            $order = $svc->createOrder([
                'tenant_id' => $user->company_id, 'currency' => 'MGA', 'created_by' => $user->id,
                'lines'     => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10000]],
            ]);
            $order->update(['status' => 'confirmed']);
            $dep->requestDeposit($order, 30, $user->id);
        }
    };

    $query = fn () => SalesOrder::forTenant($user->company_id)->with([
        'lines', 'createdBy:id,name,email',
        'depositInvoice:id,status,total,amount_paid', 'balanceInvoice:id,status,total,amount_paid',
        'contact:id,first_name,last_name', 'account:id,name',
    ])->latest();

    $buildOrders(4);
    DB::flushQueryLog();
    DB::enableQueryLog();
    foreach ($query()->get() as $order) {
        $order->toArray(); // forces payment_stage's depositInvoice/balanceInvoice access
    }
    $queryCountFor4 = count(DB::getQueryLog());

    $buildOrders(4); // now 8 orders total
    DB::flushQueryLog();
    foreach ($query()->get() as $order) {
        $order->toArray();
    }
    $queryCountFor8 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A real N+1 would roughly double the query count going from 4 to 8
    // orders (2 relations × N extra orders). A flat query count confirms
    // the eager-load fix holds.
    expect($queryCountFor8)->toBe($queryCountFor4);

    // And re-confirm the pre-fix shape really was an N+1: the same query
    // WITHOUT the deposit/balance eager loads scales with order count.
    $withoutFix = fn () => SalesOrder::forTenant($user->company_id)->with(['lines', 'createdBy:id,name,email'])->latest();
    DB::flushQueryLog();
    DB::enableQueryLog();
    foreach ($withoutFix()->get() as $order) {
        $order->toArray();
    }
    $unfixedCountFor8 = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($unfixedCountFor8)->toBeGreaterThan($queryCountFor8);
});

// ─── Layer 3 — the 2 previously-dead-link web pages are now reachable ───────

test('the create-order web page is reachable and creates a real order end to end', function () {
    $user = salesDeepAuditUser('CreatePage');

    test()->actingAs($user)
        ->get('/sales/orders/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sales/Orders/Create', false));

    // Real API round trip matching what Create.vue actually submits.
    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/sales/orders', [
        'currency' => 'MGA',
        'notes'    => 'Créée depuis le formulaire',
        'lines'    => [['description' => 'T-shirt', 'quantity' => 10, 'unit_price' => 8000, 'discount_percent' => 0]],
    ])->assertCreated();

    expect((float) $response->json('total'))->toBe(80000.0);
});

test('the edit-order web page is reachable and updates header fields on a draft order', function () {
    $user  = salesDeepAuditUser('EditPage');
    $order = app(SalesService::class)->createOrder([
        'tenant_id' => $user->company_id, 'currency' => 'MGA', 'created_by' => $user->id,
        'lines'     => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    test()->actingAs($user)
        ->get("/sales/orders/{$order->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Orders/Edit', false)
            ->where('orderId', $order->id));

    test()->actingAs($user, 'sanctum')->putJson("/api/v1/sales/orders/{$order->id}", ['notes' => 'Modifiée'])
        ->assertOk()
        ->assertJsonPath('notes', 'Modifiée');
});

// ─── Layer 13 — AI Assisted First: the 3 new grounded actions ───────────────

test('AiContextualAssistantService has real grounded fallback text for the 3 newly-wired Sales screens, fr and en', function () {
    $svc = app(\Modules\AI\Services\AiContextualAssistantService::class);
    $mods = $svc->supportedModules();

    expect($mods['Sales'])->toContain('manage_deposit_balance')
        ->toContain('manage_recurring_orders')
        ->toContain('manage_sales_objectives');

    foreach (['manage_deposit_balance', 'manage_recurring_orders', 'manage_sales_objectives'] as $action) {
        $fr = $svc->fallbackGuidance('Sales', $action, 'fr');
        $en = $svc->fallbackGuidance('Sales', $action, 'en');
        expect($fr['what_to_do'])->not->toBeEmpty();
        expect($en['what_to_do'])->not->toBeEmpty();
        expect($fr['what_to_do'])->not->toBe($en['what_to_do']);
    }
});

test('the real /api/v1/ai/assist endpoint (the one useAiAssistant() actually calls) returns real Sales guidance', function () {
    $user = salesDeepAuditUser('AiAssist');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
        'module' => 'Sales',
        'action' => 'manage_deposit_balance',
        'locale' => 'fr',
    ])->assertOk();

    expect($response->json('what_to_do'))->not->toBeEmpty();
});

// ─── Layer 13 (Chantier 26 volet B) spot-check — objectives still grounded ──

test('sales objective proposals remain grounded in real historical order data, never invented', function () {
    $user = salesDeepAuditUser('ObjectiveGrounded');
    $svc  = app(SalesService::class);

    foreach ([90, 60, 30] as $daysAgo) {
        $order = $svc->createOrder([
            'tenant_id' => $user->company_id, 'created_by' => $user->id, 'currency' => 'MGA',
            'lines'     => [['description' => 'x', 'quantity' => 1, 'unit_price' => 50000]],
        ]);
        $order->update(['status' => 'confirmed', 'confirmed_at' => now()->subDays($daysAgo)]);
    }

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/sales/objectives/propose', [
        'scope'        => 'global',
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end'   => now()->addMonth()->endOfMonth()->toDateString(),
    ])->assertCreated();

    // 50,000/month average — Conservateur must equal the real average, not
    // an arbitrary/invented number.
    expect((float) $response->json('data.0.target_amount'))->toBe(50000.0);
});
