<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\SalesService;

/**
 * Chantier 19 — Sales module empirical re-verification pass.
 *
 * Re-verifies (rather than assumes) the two tenant-scoping fixes already
 * documented in CLAUDE.md — Chantier 8.5-light's SalesController tenant-leak
 * fix (orders) and Chantier 10's zero-permission/zero-scoping fix (all 6
 * quotation endpoints + updateOrderStatus) — over the real HTTP path with two
 * genuinely distinct companies, and locks in two real, previously-undetected
 * bugs found by actually running the code against real seeded data rather
 * than reading it:
 *
 *  1. SalesService::convertQuotationToOrder() never passed a `lines` array
 *     to createOrder() (SalesQuotation has no line-item model of its own,
 *     only a flat `total`) — every converted quotation silently produced a
 *     real $0 order with zero lines, discarding the quotation's own total.
 *     Confirmed via a real HTTP round trip before the fix (order.total was
 *     "0.00" for a 75000 XOF quotation), fixed by carrying the quotation's
 *     total forward as a single synthetic order line.
 *
 *  2. SalesIndex.vue's KPI cards read data.meta.{month_count,month_revenue,
 *     pending_count,delivered_count} from GET /sales/orders, but the
 *     controller's response()->json($orders) on a raw paginator never
 *     carried a 'meta' key at all (Laravel's default paginator JSON
 *     structure flattens pagination fields to the top level) — confirmed via
 *     a real HTTP call returning no 'meta' key whatsoever, meaning every KPI
 *     card on the Sales dashboard has always silently shown '0'/'0 XOF'.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function salesReauditUser(Company $company, string $role = 'sales-manager'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ─── Bug 1: quotation → order conversion silently discarded the total ────────

test('converting a quotation preserves its total on the resulting order', function () {
    $company = Company::factory()->create();
    $user    = salesReauditUser($company);

    /** @var SalesService $svc */
    $svc   = app(SalesService::class);
    $quote = $svc->createQuotation([
        'tenant_id'  => $company->id,
        'currency'   => 'XOF',
        'total'      => 75000,
        'created_by' => $user->id,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/quotations/{$quote->id}/convert")
        ->assertOk();

    expect((float) $response->json('order.total'))->toBe(75000.0)
        ->and($response->json('order.lines'))->toHaveCount(1);

    $order = SalesOrder::findOrFail($response->json('order.id'));
    expect((float) $order->total)->toBe(75000.0);
    expect($order->lines()->count())->toBe(1);
});

// ─── Bug 2: dashboard KPI meta was silently missing from the list response ───

test('GET sales/orders returns real KPI meta for the SalesIndex dashboard cards', function () {
    $company = Company::factory()->create();
    $user    = salesReauditUser($company);

    /** @var SalesService $svc */
    $svc = app(SalesService::class);
    $svc->createOrder([
        'tenant_id'  => $company->id,
        'currency'   => 'XOF',
        'created_by' => $user->id,
        'lines'      => [['description' => 'Item', 'quantity' => 2, 'unit_price' => 5000]],
    ]);
    $delivered = $svc->createOrder([
        'tenant_id'  => $company->id,
        'currency'   => 'XOF',
        'created_by' => $user->id,
        'lines'      => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 3000]],
    ]);
    $delivered->update(['status' => 'delivered']);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/sales/orders')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['month_count', 'month_revenue', 'pending_count', 'delivered_count']]);

    expect($response->json('meta.month_count'))->toBe(2);
    expect((float) $response->json('meta.month_revenue'))->toBe(13000.0);
    expect($response->json('meta.pending_count'))->toBe(1);
    expect($response->json('meta.delivered_count'))->toBe(1);
});

// ─── Re-verification: order tenant scoping (Chantier 8.5-light) ──────────────

test('a user from company A cannot read, update, confirm, or cancel company B orders', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA    = salesReauditUser($companyA);

    /** @var SalesService $svc */
    $svc      = app(SalesService::class);
    $orderB   = $svc->createOrder([
        'tenant_id'  => $companyB->id,
        'currency'   => 'XOF',
        'created_by' => $userA->id,
        'lines'      => [['description' => 'B item', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $api = test()->actingAs($userA, 'sanctum');

    $api->getJson("/api/v1/sales/orders/{$orderB->id}")->assertNotFound();
    $api->putJson("/api/v1/sales/orders/{$orderB->id}", ['notes' => 'hacked'])->assertNotFound();
    $api->postJson("/api/v1/sales/orders/{$orderB->id}/confirm")->assertNotFound();
    $api->postJson("/api/v1/sales/orders/{$orderB->id}/cancel", ['reason' => 'x'])->assertNotFound();
    $api->putJson("/api/v1/sales/orders/{$orderB->id}/status", ['status' => 'confirmed'])->assertNotFound();

    // Company B's order is untouched.
    expect($orderB->fresh()->status)->toBe('draft');
    expect($orderB->fresh()->notes)->not->toBe('hacked');

    // And does not leak into company A's listing either.
    $list = $api->getJson('/api/v1/sales/orders')->assertOk();
    expect(collect($list->json('data'))->pluck('id'))->not->toContain($orderB->id);
});

// ─── Re-verification: quotation tenant scoping (Chantier 10) ─────────────────

test('a user from company A cannot read, update, send, or convert company B quotations', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA    = salesReauditUser($companyA);

    /** @var SalesService $svc */
    $svc     = app(SalesService::class);
    $quoteB  = $svc->createQuotation([
        'tenant_id'  => $companyB->id,
        'currency'   => 'XOF',
        'total'      => 42000,
        'created_by' => $userA->id,
    ]);

    $api = test()->actingAs($userA, 'sanctum');

    $api->getJson("/api/v1/sales/quotations/{$quoteB->id}")->assertNotFound();
    $api->putJson("/api/v1/sales/quotations/{$quoteB->id}", ['total' => 1])->assertNotFound();
    $api->postJson("/api/v1/sales/quotations/{$quoteB->id}/send")->assertNotFound();
    $api->postJson("/api/v1/sales/quotations/{$quoteB->id}/convert")->assertNotFound();

    // Company B's quotation is untouched — no order was ever created from it.
    $quoteB->refresh();
    expect($quoteB->status)->toBe('draft');
    expect((float) $quoteB->total)->toBe(42000.0);
    expect($quoteB->converted_to_order_id)->toBeNull();

    $list = $api->getJson('/api/v1/sales/quotations')->assertOk();
    expect(collect($list->json('data'))->pluck('id'))->not->toContain($quoteB->id);
});

// ─── Re-verification: an employee with zero sales permission is denied ───────

test('an employee-role-less user without sales permission cannot reach any sales endpoint', function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // sales-rep is documented (RolesAndPermissionsSeeder's own docblock) as
    // "full access to CRM module only" — it deliberately carries zero
    // sales.* permissions, matching the module's real seeded shape.
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales-rep', 'guard_name' => 'web']);
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('sales-rep');

    $api = test()->actingAs($user, 'sanctum');

    $api->getJson('/api/v1/sales/orders')->assertForbidden();
    $api->getJson('/api/v1/sales/quotations')->assertForbidden();
});
