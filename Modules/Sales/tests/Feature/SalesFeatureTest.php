<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\SalesService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function salesUser(): User
{
    return actingAsUser('admin');
}

function makeSalesOrder(User $user, array $overrides = []): SalesOrder
{
    return SalesOrder::create(array_merge([
        'tenant_id'  => $user->id,
        'reference'  => 'SO-TEST-' . uniqid(),
        'status'     => 'draft',
        'currency'   => 'XOF',
        'subtotal'   => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total'      => 0,
        'created_by' => $user->id,
    ], $overrides));
}

// ─── Authentication ────────────────────────────────────────────────────────────

test('unauthenticated request to sales orders returns 401', function () {
    $this->getJson('/api/v1/sales/orders')
        ->assertUnauthorized();
});

test('unauthenticated request to sales quotations returns 401', function () {
    $this->getJson('/api/v1/sales/quotations')
        ->assertUnauthorized();
});

// ─── API CRUD ──────────────────────────────────────────────────────────────────

test('authenticated user can list sales orders', function () {
    salesUser();

    $this->getJson('/api/v1/sales/orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('can create a sales order via API', function () {
    salesUser();

    $this->postJson('/api/v1/sales/orders', [
        'currency' => 'XOF',
        'lines'    => [
            [
                'description' => 'Widget A',
                'quantity'    => 3,
                'unit_price'  => 5000,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.currency', 'XOF');
});

test('creating a sales order without lines returns 422', function () {
    salesUser();

    $this->postJson('/api/v1/sales/orders', [
        'currency' => 'XOF',
    ])
        ->assertUnprocessable();
});

// ─── SalesOrder Model ──────────────────────────────────────────────────────────

test('sales order reference starts with SO-', function () {
    $user  = salesUser();
    $order = app(SalesService::class)->createOrder([
        'tenant_id'  => $user->id,
        'currency'   => 'XOF',
        'created_by' => $user->id,
        'lines'      => [
            ['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000],
        ],
    ]);

    expect($order->reference)->toStartWith('SO-');
});

test('sales order is editable when draft', function () {
    $user  = salesUser();
    $order = makeSalesOrder($user, ['status' => 'draft']);

    expect($order->isEditable())->toBeTrue();
});

test('sales order is not editable when confirmed', function () {
    $user  = salesUser();
    $order = makeSalesOrder($user, ['status' => 'confirmed']);

    expect($order->isEditable())->toBeFalse();
});

test('confirming a draft order changes status to confirmed', function () {
    $user    = salesUser();
    $service = app(SalesService::class);

    $order = $service->createOrder([
        'tenant_id'  => $user->id,
        'currency'   => 'XOF',
        'created_by' => $user->id,
        'lines'      => [
            ['description' => 'Item B', 'quantity' => 2, 'unit_price' => 2500],
        ],
    ]);

    $confirmed = $service->confirmOrder($order);

    expect($confirmed->status)->toBe('confirmed')
        ->and($confirmed->confirmed_at)->not->toBeNull();
});

test('cancelling an order changes status to cancelled', function () {
    $user    = salesUser();
    $service = app(SalesService::class);

    $order = $service->createOrder([
        'tenant_id'  => $user->id,
        'currency'   => 'XOF',
        'created_by' => $user->id,
        'lines'      => [
            ['description' => 'Item C', 'quantity' => 1, 'unit_price' => 1000],
        ],
    ]);

    $cancelled = $service->cancelOrder($order, 'Customer request');

    expect($cancelled->status)->toBe('cancelled')
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

// ─── SalesQuotation ────────────────────────────────────────────────────────────

test('can create a quotation via service', function () {
    $user    = salesUser();
    $service = app(SalesService::class);

    $quotation = $service->createQuotation([
        'tenant_id'   => $user->id,
        'currency'    => 'XOF',
        'created_by'  => $user->id,
        'valid_until' => now()->addDays(30)->toDateString(),
        'lines'       => [
            ['description' => 'Service Package', 'quantity' => 1, 'unit_price' => 50000],
        ],
    ]);

    expect($quotation->reference)->toStartWith('QT-')
        ->and($quotation->status)->toBe('draft');
});

test('converting a quotation creates a sales order', function () {
    $user    = salesUser();
    $service = app(SalesService::class);

    $quotation = $service->createQuotation([
        'tenant_id'   => $user->id,
        'currency'    => 'XOF',
        'created_by'  => $user->id,
        'valid_until' => now()->addDays(14)->toDateString(),
        'lines'       => [
            ['description' => 'Product X', 'quantity' => 5, 'unit_price' => 10000],
        ],
    ]);

    $order = $service->convertQuotationToOrder($quotation);

    expect($order)->toBeInstanceOf(SalesOrder::class)
        ->and($order->status)->toBe('draft');

    $quotation->refresh();
    expect($quotation->converted_to_order_id)->toBe($order->id);
});

// ─── Tenant Isolation ─────────────────────────────────────────────────────────

test('sales order from another tenant is not listed', function () {
    $user = salesUser();

    SalesOrder::create([
        'tenant_id'      => 9999,
        'reference'      => 'SO-FOREIGN-001',
        'status'         => 'draft',
        'currency'       => 'EUR',
        'subtotal'       => 100,
        'discount_amount'=> 0,
        'tax_amount'     => 0,
        'total'          => 100,
        'created_by'     => 9999,
    ]);

    $response = $this->getJson('/api/v1/sales/orders')->assertOk();
    $refs     = collect($response->json('data'))->pluck('reference');

    expect($refs->contains('SO-FOREIGN-001'))->toBeFalse();
});
