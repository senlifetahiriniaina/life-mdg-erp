<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\JournalEntry;
use Modules\Sales\Models\SalesOrder;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 22 (volet B de la feuille de route Chantier 21) — cycle
 * acompte/solde côté vente, sur la vraie route HTTP de bout en bout : une
 * fiche de chiffrage/commande génère un acompte réel, l'acompte est
 * encaissé, le solde est facturé puis encaissé, avec une vraie écriture
 * comptable balancée sur les comptes OHADA 419/411.
 */
function depositBalanceUser(string $suffix): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // The chart of accounts / journals (VTE, 419, 411, 512) are only seeded
    // by AccountingDatabaseSeeder, not by RolesAndPermissionsSeeder —
    // actingAsUser()-style helpers in this app only auto-seed the latter
    // (same precedent as CostingSheetTest's DefaultDataSeeder gap). Without
    // this, postJournalEntry() throws "Plan comptable incomplet" on every
    // payment — confirmed empirically by a first, failing test run.
    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name' => "Chantier22 Sales Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('sales-manager');

    return $user;
}

function depositBalanceOrder(User $user, float $total = 1000000): SalesOrder
{
    return SalesOrder::create([
        'tenant_id' => $user->company_id,
        'reference' => 'SO-' . uniqid(),
        'status' => 'confirmed',
        'currency' => 'MGA',
        'subtotal' => $total,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total' => $total,
        'created_by' => $user->id,
    ]);
}

test('requesting a deposit creates a real linked invoice and snapshot amount', function () {
    $user = depositBalanceUser('A');
    $order = depositBalanceOrder($user);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    expect((float) $response->json('deposit_percent'))->toBe(30.0)
        ->and((float) $response->json('deposit_required_amount'))->toBe(300000.0)
        ->and($response->json('deposit_invoice_id'))->not->toBeNull()
        ->and($response->json('payment_stage'))->toBe('deposit_invoiced');

    $order->refresh();
    expect($order->depositInvoice)->not->toBeNull();
    expect((float) $order->depositInvoice->total)->toBe(300000.0);
});

test('paying the deposit then requesting and paying the balance completes the cycle with real journal entries', function () {
    $user = depositBalanceUser('B');
    $order = depositBalanceOrder($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    $order->refresh();

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", [
            'amount' => 300000,
            'method' => 'mvola',
            'reference' => 'MVOLA-TEST',
        ])
        ->assertOk();

    expect($response->json('payment_stage'))->toBe('deposit_paid');

    $order->refresh();
    expect((float) $order->depositInvoice->amount_paid)->toBe(300000.0)
        ->and($order->depositInvoice->status)->toBe('paid');

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/balance/request")
        ->assertOk();

    expect((float) $response->json('balance_invoice_id'))->not->toBeNull();

    $order->refresh();
    expect((float) $order->balanceInvoice->total)->toBe(700000.0);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/balance/pay", ['amount' => 700000, 'method' => 'virement'])
        ->assertOk();

    expect($response->json('payment_stage'))->toBe('paid_in_full');

    // Deux écritures réelles et balancées (Débit 512 Banque / Crédit 419 puis 411).
    $entries = JournalEntry::where('reference_type', SalesOrder::class)->where('reference_id', $order->id)->with('lines.account')->get();
    expect($entries)->toHaveCount(2);

    $deposit = $entries->first(fn ($e) => str_contains($e->description, 'Acompte'));
    $balance = $entries->first(fn ($e) => str_contains($e->description, 'Solde'));

    expect((float) $deposit->lines->sum('debit'))->toBe(300000.0)
        ->and((float) $deposit->lines->sum('credit'))->toBe(300000.0)
        ->and($deposit->lines->firstWhere('credit', 300000)->account->code)->toBe('419');

    expect((float) $balance->lines->sum('debit'))->toBe(700000.0)
        ->and($balance->lines->firstWhere('credit', 700000)->account->code)->toBe('41');
});

test('a second deposit request on an already-requested order is rejected', function () {
    $user = depositBalanceUser('C');
    $order = depositBalanceOrder($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertStatus(422);
});

test('overpaying the deposit invoice is rejected', function () {
    $user = depositBalanceUser('D');
    $order = depositBalanceOrder($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 999999999])
        ->assertStatus(422);
});

test('a user with no sales permission is denied requesting a deposit', function () {
    $company = Company::create(['name' => 'No Perm Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $order = depositBalanceOrder($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertForbidden();
});

test('a company cannot request a deposit on another company\'s order', function () {
    $userA = depositBalanceUser('E1');
    $userB = depositBalanceUser('E2');
    $orderA = depositBalanceOrder($userA);

    test()->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$orderA->id}/deposit/request", ['percent' => 30])
        ->assertNotFound();
});

test('Chantier 37: with no override, the default_treasury_account/default_clients_account/avances_recues_clients roles produce the same journal accounts as before', function () {
    // Regression: AccountRoleService::DEFAULT_ROLES mirrors exactly what this
    // service hardcoded before Chantier 37 — 512/419/411 (via the account
    // roles' current default codes 52/419/41) unless a tenant customizes.
    $user = depositBalanceUser('G');
    $order = depositBalanceOrder($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertOk();
    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 300000])
        ->assertOk();

    $order->refresh();
    $entry = JournalEntry::where('reference_type', SalesOrder::class)->where('reference_id', $order->id)
        ->with('lines.account')->first();

    expect($entry->lines->firstWhere('debit', 300000)->account->code)->toBe('52');
    expect($entry->lines->firstWhere('credit', 300000)->account->code)->toBe('419');
});

test('Chantier 37: overriding default_treasury_account/avances_recues_clients changes which accounts the deposit is posted to', function () {
    $user = depositBalanceUser('H');
    test()->actingAs($user, 'sanctum');

    app(\Modules\Accounting\Services\AccountRoleService::class)->setRole('default_treasury_account', '57');
    app(\Modules\Accounting\Services\AccountRoleService::class)->setRole('avances_recues_clients', '47');

    $order = depositBalanceOrder($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertOk();
    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 300000])
        ->assertOk();

    $order->refresh();
    $entry = JournalEntry::where('reference_type', SalesOrder::class)->where('reference_id', $order->id)
        ->with('lines.account')->first();

    expect($entry->lines->firstWhere('debit', 300000)->account->code)->toBe('57');
    expect($entry->lines->firstWhere('credit', 300000)->account->code)->toBe('47');
});

test('the order detail web page (deposit/balance panel) is reachable', function () {
    $user = depositBalanceUser('F');
    $order = depositBalanceOrder($user);

    // component(..., false) skips inertia-laravel's own page-exists finder,
    // which doesn't understand this app's custom module-prefixed resolve()
    // — same established workaround as CostingSheetTest (Chantier 21).
    test()->actingAs($user)
        ->get("/sales/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Orders/Show', false)
            ->where('orderId', $order->id)
        );
});
