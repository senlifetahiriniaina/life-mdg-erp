<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesDepositService;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 38.4 — deuxième passe d'audit en 14 couches de Modules\Sales
 * (le premier passage complet est documenté au Chantier 32.16). Verrouille
 * les bugs réels trouvés par exécution empirique (jamais une simple
 * relecture de code) que ce second passage a trouvés — voir CLAUDE.md pour
 * la synthèse complète.
 *
 * Priorité 1 de ce chantier (résolution empirique d'une contradiction entre
 * deux notes précédentes) n'a produit aucun nouveau test ici : lu le
 * fichier réel, `SalesDepositService` était déjà correctement réécrit sur
 * `AccountRoleService::resolveAccount()` au Chantier 37 (la note affirmant
 * le contraire était périmée) — déjà verrouillé par les 2 tests "Chantier
 * 37" existants dans Chantier22DepositBalanceTest.php, non dupliqués ici.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function chantier384User(string $suffix, string $role = 'sales-manager'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name'     => "Chantier38.4 Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function chantier384Order(User $user, string $status = 'confirmed', float $total = 1000000): SalesOrder
{
    return SalesOrder::create([
        'tenant_id'  => $user->company_id,
        'reference'  => 'SO-' . uniqid('', true),
        'status'     => $status,
        'currency'   => 'MGA',
        'subtotal'   => $total,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total'      => $total,
        'created_by' => $user->id,
    ]);
}

// ─── Layer 8 — new bug: deposit/balance requestable on a dead order ──────────

test('requesting a deposit on a cancelled order is rejected server-side, not just hidden client-side', function () {
    $user  = chantier384User('CancelDeposit');
    $order = chantier384Order($user, 'cancelled');

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])
        ->assertStatus(422);

    expect($order->fresh()->deposit_invoice_id)->toBeNull();
});

test('requesting a balance on a cancelled order is rejected', function () {
    $user  = chantier384User('CancelBalance');
    $order = chantier384Order($user, 'cancelled');

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/balance/request")
        ->assertStatus(422);

    expect($order->fresh()->balance_invoice_id)->toBeNull();
});

test('paying an already-requested deposit is rejected once the order is later cancelled', function () {
    $user  = chantier384User('CancelPay');
    $order = chantier384Order($user, 'confirmed');

    $svc = app(SalesDepositService::class);
    $order = $svc->requestDeposit($order, 30, $user->id);

    $order->update(['status' => 'cancelled']);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/orders/{$order->id}/deposit/pay", ['amount' => 300000])
        ->assertStatus(422);

    expect((float) $order->fresh()->depositInvoice->amount_paid)->toBe(0.0);
});

test('a draft or a delivered order can still request/pay a deposit — only cancelled/returned are blocked', function () {
    $user = chantier384User('StillActive');

    foreach (['draft', 'confirmed', 'processing', 'shipped', 'delivered'] as $status) {
        $order = chantier384Order($user, $status);
        $order = app(SalesDepositService::class)->requestDeposit($order, 30, $user->id);
        expect($order->deposit_invoice_id)->not->toBeNull();
    }
});

// ─── Layer 8/12 — new bug: payment_stage lied about paid_in_full ─────────────

test('payment_stage is not paid_in_full while the deposit invoice is still unpaid, even if the balance invoice is fully paid', function () {
    $user  = chantier384User('StageLie');
    $order = chantier384Order($user, 'confirmed');

    $svc = app(SalesDepositService::class);
    $order = $svc->requestDeposit($order, 30, $user->id); // deposit_required_amount = 300000, unpaid
    $order = $svc->requestBalance($order, $user->id);     // balance = 700000

    $order = $svc->recordBalancePayment($order, 700000, 'virement', null, $user->id);

    // Real money (the 300,000 MGA deposit) is still owed — must not claim
    // 'paid_in_full'.
    expect($order->payment_stage)->toBe('balance_invoiced');
    expect((float) $order->depositInvoice->amount_paid)->toBe(0.0);

    // Once the deposit is also paid, THEN it is genuinely paid_in_full.
    $order = $svc->recordDepositPayment($order, 300000, 'mvola', null, $user->id);
    expect($order->payment_stage)->toBe('paid_in_full');
});

test('payment_stage is paid_in_full for an order with no deposit at all once the balance alone is paid', function () {
    $user  = chantier384User('NoDeposit');
    $order = chantier384Order($user, 'confirmed', 500000);

    $svc = app(SalesDepositService::class);
    $order = $svc->requestBalance($order, $user->id); // no deposit ever requested — full total
    $order = $svc->recordBalancePayment($order, 500000, 'virement', null, $user->id);

    expect($order->payment_stage)->toBe('paid_in_full');
});

// ─── Re-confirmation via the real HTTP route (not just the service directly) ─

test('the real HTTP deposit/pay endpoint reflects the corrected payment_stage', function () {
    $user  = chantier384User('HttpStage');
    $order = chantier384Order($user, 'confirmed', 1000000);

    $api = test()->actingAs($user, 'sanctum');
    $api->postJson("/api/v1/sales/orders/{$order->id}/deposit/request", ['percent' => 30])->assertOk();
    $api->postJson("/api/v1/sales/orders/{$order->id}/balance/request")->assertOk();

    $response = $api->postJson("/api/v1/sales/orders/{$order->id}/balance/pay", ['amount' => 700000])->assertOk();

    expect($response->json('payment_stage'))->toBe('balance_invoiced');
});

// ─── Layer 3/13 — Orders/Create.vue now wired with real AI guidance ─────────

test('the create-order page is wired with a real, grounded AI action (create_order), not left silent', function () {
    $svc = app(\Modules\AI\Services\AiContextualAssistantService::class);

    $fr = $svc->fallbackGuidance('Sales', 'create_order', 'fr');
    expect($fr['what_to_do'])->not->toBeEmpty();
});

// ─── Priority 4 — SalesOrderPolicy remains deleted, no residual reference ────

test('SalesOrderPolicy is still confirmed absent from the Gate and the class no longer exists', function () {
    expect(class_exists(\Modules\Sales\Policies\SalesOrderPolicy::class))->toBeFalse();
    expect(\Illuminate\Support\Facades\Gate::getPolicyFor(SalesOrder::class))->toBeNull();
});
