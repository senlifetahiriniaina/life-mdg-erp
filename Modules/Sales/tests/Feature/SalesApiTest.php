<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\SalesService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function apiUser(): \App\Models\User
{
    return actingAsUser('admin');
}

function makeQuote(\App\Models\User $user, array $overrides = []): SalesQuotation
{
    /** @var SalesService $svc */
    $svc = app(SalesService::class);

    return $svc->createQuotation(array_merge([
        'tenant_id'  => $user->tenant_id ?? $user->id,
        'currency'   => 'XOF',
        'total'      => 50000,
        'created_by' => $user->id,
    ], $overrides));
}

function makeSalesApiOrder(\App\Models\User $user, array $overrides = []): SalesOrder
{
    /** @var SalesService $svc */
    $svc = app(SalesService::class);

    return $svc->createOrder(array_merge([
        'tenant_id'  => $user->tenant_id ?? $user->id,
        'currency'   => 'XOF',
        'created_by' => $user->id,
        'lines'      => [
            ['description' => 'Default Item', 'quantity' => 1, 'unit_price' => 10000],
        ],
    ], $overrides));
}

// ─── 1. Auth required ─────────────────────────────────────────────────────────

test('unauthenticated GET /sales/orders returns 401', function () {
    $this->getJson('/api/v1/sales/orders')
        ->assertUnauthorized();
});

test('unauthenticated GET /sales/quotations returns 401', function () {
    $this->getJson('/api/v1/sales/quotations')
        ->assertUnauthorized();
});

test('unauthenticated POST /sales/orders returns 401', function () {
    $this->postJson('/api/v1/sales/orders', [])
        ->assertUnauthorized();
});

test('unauthenticated POST /sales/quotations returns 401', function () {
    $this->postJson('/api/v1/sales/quotations', [])
        ->assertUnauthorized();
});

// ─── 2. Quote CRUD ────────────────────────────────────────────────────────────

test('authenticated user can list quotations', function () {
    apiUser();

    $this->getJson('/api/v1/sales/quotations')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('can create a quotation via API', function () {
    apiUser();

    $this->postJson('/api/v1/sales/quotations', [
        'currency'    => 'XOF',
        'total'       => 120000,
        'valid_until' => now()->addDays(30)->toDateString(),
        'notes'       => 'Test quotation',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('currency', 'XOF');
});

test('can retrieve a single quotation by id', function () {
    $user  = apiUser();
    $quote = makeQuote($user);

    $this->getJson("/api/v1/sales/quotations/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('id', $quote->id)
        ->assertJsonPath('reference', $quote->reference);
});

test('can update a draft quotation via API', function () {
    $user  = apiUser();
    $quote = makeQuote($user);

    $this->putJson("/api/v1/sales/quotations/{$quote->id}", [
        'total' => 99000,
        'notes' => 'Updated notes',
    ])
        ->assertOk()
        ->assertJsonPath('id', $quote->id)
        ->assertJsonPath('notes', 'Updated notes');
});

test('updating a non-draft quotation returns 422', function () {
    $user  = apiUser();
    $quote = makeQuote($user);
    $quote->update(['status' => 'sent']);

    $this->putJson("/api/v1/sales/quotations/{$quote->id}", ['total' => 5000])
        ->assertUnprocessable();
});

test('can mark a draft quotation as sent via POST send', function () {
    $user  = apiUser();
    $quote = makeQuote($user);

    $this->postJson("/api/v1/sales/quotations/{$quote->id}/send")
        ->assertOk()
        ->assertJsonPath('status', 'sent');
});

test('sending an already-sent quotation returns 422', function () {
    $user  = apiUser();
    $quote = makeQuote($user);
    $quote->update(['status' => 'sent']);

    $this->postJson("/api/v1/sales/quotations/{$quote->id}/send")
        ->assertUnprocessable();
});

// ─── 3. Quote → Order conversion ─────────────────────────────────────────────

test('converting a draft quotation creates a sales order', function () {
    $user  = apiUser();
    $quote = makeQuote($user, ['total' => 75000]);

    $response = $this->postJson("/api/v1/sales/quotations/{$quote->id}/convert")
        ->assertOk()
        ->assertJsonStructure(['message', 'order']);

    expect($response->json('order.status'))->toBe('draft');
    expect($response->json('order.currency'))->toBe('XOF');

    $this->assertDatabaseHas('sales_quotations', [
        'id'     => $quote->id,
        'status' => 'accepted',
    ]);
});

test('converting an already-converted quotation returns 422', function () {
    $user  = apiUser();
    $quote = makeQuote($user);

    // First conversion
    $this->postJson("/api/v1/sales/quotations/{$quote->id}/convert")->assertOk();

    // Second conversion must fail
    $this->postJson("/api/v1/sales/quotations/{$quote->id}/convert")
        ->assertUnprocessable();
});

test('converting a rejected quotation returns 422', function () {
    $user  = apiUser();
    $quote = makeQuote($user);
    $quote->update(['status' => 'rejected']);

    $this->postJson("/api/v1/sales/quotations/{$quote->id}/convert")
        ->assertUnprocessable();
});

// ─── 4. Order CRUD ────────────────────────────────────────────────────────────

test('authenticated user can list orders', function () {
    apiUser();

    $this->getJson('/api/v1/sales/orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('can create a sales order with lines via API', function () {
    apiUser();

    $this->postJson('/api/v1/sales/orders', [
        'currency' => 'EUR',
        'lines'    => [
            ['description' => 'Widget A', 'quantity' => 5, 'unit_price' => 20],
            ['description' => 'Widget B', 'quantity' => 2, 'unit_price' => 100],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('currency', 'EUR')
        ->assertJsonStructure(['id', 'reference', 'status', 'total', 'lines']);
});

test('creating an order without lines returns 422', function () {
    apiUser();

    $this->postJson('/api/v1/sales/orders', ['currency' => 'XOF'])
        ->assertUnprocessable();
});

test('can show a single order by id', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $this->getJson("/api/v1/sales/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('id', $order->id)
        ->assertJsonStructure(['id', 'reference', 'status', 'lines']);
});

// ─── 5. Order status transitions ─────────────────────────────────────────────

test('PUT /orders/{id}/status confirms a draft order', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');
});

test('PUT /orders/{id}/status cancels a draft order', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", [
        'status' => 'cancelled',
        'reason' => 'Customer withdrawn',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');
});

test('PUT /orders/{id}/status ships a processing order', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    // draft → confirmed → processing → shipped
    app(SalesService::class)->confirmOrder($order);
    $order->update(['status' => 'processing']);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'shipped'])
        ->assertOk()
        ->assertJsonPath('status', 'shipped');
});

test('PUT /orders/{id}/status delivers a shipped order', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $order->update(['status' => 'shipped']);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'delivered'])
        ->assertOk()
        ->assertJsonPath('status', 'delivered');
});

// ─── 6. Invalid status transitions → 422 ─────────────────────────────────────

test('invalid status transition from draft to shipped returns 422', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'shipped'])
        ->assertUnprocessable()
        ->assertJsonStructure(['message']);
});

test('invalid status transition from delivered to cancelled returns 422', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);
    $order->update(['status' => 'delivered']);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'cancelled'])
        ->assertUnprocessable();
});

test('transition with unsupported status value returns 422', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $this->putJson("/api/v1/sales/orders/{$order->id}/status", ['status' => 'refunded'])
        ->assertUnprocessable();
});

test('confirming an already-confirmed order via legacy endpoint returns 422', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);
    app(SalesService::class)->confirmOrder($order);

    $this->postJson("/api/v1/sales/orders/{$order->id}/confirm")
        ->assertUnprocessable();
});

// ─── 7. Tenant isolation ─────────────────────────────────────────────────────

test('orders from another tenant are not visible in listing', function () {
    $user = apiUser();

    // Create an order belonging to a different tenant
    SalesOrder::create([
        'tenant_id'       => 9999,
        'reference'       => 'SO-FOREIGN-9999',
        'status'          => 'draft',
        'currency'        => 'EUR',
        'subtotal'        => 500,
        'discount_amount' => 0,
        'tax_amount'      => 0,
        'total'           => 500,
        'created_by'      => 9999,
    ]);

    $response = $this->getJson('/api/v1/sales/orders')->assertOk();
    $refs     = collect($response->json('data'))->pluck('reference');

    expect($refs->contains('SO-FOREIGN-9999'))->toBeFalse();
});

test('quotations from another tenant are not visible in listing', function () {
    $user = apiUser();

    SalesQuotation::create([
        'tenant_id'  => 9999,
        'reference'  => 'QT-FOREIGN-9999',
        'status'     => 'draft',
        'currency'   => 'EUR',
        'total'      => 200,
        'created_by' => 9999,
    ]);

    $response = $this->getJson('/api/v1/sales/quotations')->assertOk();
    $refs     = collect($response->json('data'))->pluck('reference');

    expect($refs->contains('QT-FOREIGN-9999'))->toBeFalse();
});

test('own tenant orders appear in the listing', function () {
    $user  = apiUser();
    $order = makeSalesApiOrder($user);

    $response = $this->getJson('/api/v1/sales/orders')->assertOk();
    $ids      = collect($response->json('data'))->pluck('id');

    expect($ids->contains($order->id))->toBeTrue();
});

// ─── 8. Filtering & pagination ────────────────────────────────────────────────

test('orders list can be filtered by status', function () {
    $user = apiUser();
    makeSalesApiOrder($user);
    $svc   = app(SalesService::class);
    $order = makeSalesApiOrder($user);
    $svc->confirmOrder($order);

    $response = $this->getJson('/api/v1/sales/orders?status=confirmed')->assertOk();
    $statuses = collect($response->json('data'))->pluck('status');

    expect($statuses->every(fn ($s) => $s === 'confirmed'))->toBeTrue();
});

test('orders list supports per_page pagination', function () {
    $user = apiUser();

    for ($i = 0; $i < 4; $i++) {
        makeSalesApiOrder($user);
    }

    $this->getJson('/api/v1/sales/orders?per_page=2&page=1')
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page']);
});

test('quotations list can be filtered by status sent', function () {
    $user = apiUser();
    makeQuote($user);                            // draft
    $sent = makeQuote($user);
    $sent->update(['status' => 'sent']);

    $response = $this->getJson('/api/v1/sales/quotations?status=sent')->assertOk();
    $statuses = collect($response->json('data'))->pluck('status');

    expect($statuses->every(fn ($s) => $s === 'sent'))->toBeTrue();
});
