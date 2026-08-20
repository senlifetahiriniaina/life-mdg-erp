<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\JournalEntry;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 22 (volet B) — miroir côté achats de
 * Modules/Sales/tests/Feature/Chantier22DepositBalanceTest.php : commande
 * de matières avec acompte versé au fournisseur / solde à la livraison,
 * vérifié sur la vraie route HTTP avec une vraie écriture comptable sur
 * les comptes OHADA 4091/401.
 */
function depositBalancePoUser(string $suffix): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name' => "Chantier22 Achats Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('purchasing-manager');

    return $user;
}

function depositBalancePo(User $user, float $total = 500000): PurchaseOrder
{
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);

    return PurchaseOrder::create([
        'po_number' => 'PO-' . uniqid(),
        'supplier_id' => $supplier->id,
        'status' => 'approved',
        'order_date' => now(),
        'currency' => 'MGA',
        'subtotal' => $total,
        'tax_amount' => 0,
        'shipping_cost' => 0,
        'total' => $total,
        'requested_by' => $user->id,
        'created_by' => $user->id,
        'company_id' => $user->company_id,
    ]);
}

test('requesting a deposit creates a real linked bill and snapshot amount', function () {
    $user = depositBalancePoUser('A');
    $po = depositBalancePo($user);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    expect((float) $response->json('deposit_percent'))->toBe(30.0)
        ->and((float) $response->json('deposit_required_amount'))->toBe(150000.0)
        ->and($response->json('deposit_invoice_id'))->not->toBeNull()
        ->and($response->json('payment_stage'))->toBe('deposit_invoiced');

    $po->refresh();
    expect($po->depositInvoice->type)->toBe('bill');
    expect((float) $po->depositInvoice->total)->toBe(150000.0);
});

test('paying the deposit then requesting and paying the balance completes the cycle with real journal entries', function () {
    $user = depositBalancePoUser('B');
    $po = depositBalancePo($user);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/deposit/pay", [
            'amount' => 150000,
            'method' => 'virement',
            'reference' => 'VIR-DEP-1',
        ])
        ->assertOk();

    expect($response->json('payment_stage'))->toBe('deposit_paid');

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/balance/request")
        ->assertOk();

    $po->refresh();
    expect((float) $po->balanceInvoice->total)->toBe(350000.0);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/balance/pay", ['amount' => 350000, 'method' => 'virement'])
        ->assertOk();

    expect($response->json('payment_stage'))->toBe('paid_in_full');

    $entries = JournalEntry::where('reference_type', PurchaseOrder::class)->where('reference_id', $po->id)->with('lines.account')->get();
    expect($entries)->toHaveCount(2);

    $deposit = $entries->first(fn ($e) => str_contains($e->description, 'Acompte'));
    $balance = $entries->first(fn ($e) => str_contains($e->description, 'Solde'));

    expect($deposit->lines->firstWhere('debit', 150000)->account->code)->toBe('4091');
    expect($balance->lines->firstWhere('debit', 350000)->account->code)->toBe('401');
});

test('a purchasing-manager from another company cannot record a payment on this order', function () {
    $userA = depositBalancePoUser('C1');
    $userB = depositBalancePoUser('C2');
    $poA = depositBalancePo($userA);

    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$poA->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    test()->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$poA->id}/deposit/pay", ['amount' => 150000])
        ->assertNotFound();
});

test('a warehouse-operator (no achats.purchase-order.approve) is denied recording a payment', function () {
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create(['name' => 'WH Op Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('purchasing-manager');
    $operator = User::factory()->create(['company_id' => $company->id]);
    $operator->assignRole('warehouse-operator');

    $po = depositBalancePo($manager);
    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/deposit/request", ['percent' => 30])
        ->assertOk();

    test()->actingAs($operator, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/deposit/pay", ['amount' => 150000])
        ->assertForbidden();
});

test('the purchase order detail web page exposes the deposit/balance fields', function () {
    $user = depositBalancePoUser('G');
    $po = depositBalancePo($user);

    test()->actingAs($user)
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/deposit/request", ['percent' => 30]);

    // component(..., false) skips inertia-laravel's own page-exists finder,
    // which doesn't understand this app's custom module-prefixed resolve()
    // — same established workaround as CostingSheetTest (Chantier 21) and
    // the Sales-side Chantier22DepositBalanceTest.
    test()->actingAs($user)
        ->get("/purchase-orders/{$po->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Achats/PurchaseOrders/Show', false)
            ->where('purchaseOrder.deposit_invoice_id', fn ($id) => $id !== null)
            ->where('purchaseOrder.deposit_invoice.total', 150000)
        );
});
