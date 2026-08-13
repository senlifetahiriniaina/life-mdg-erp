<?php

declare(strict_types=1);

use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\SalesService;

beforeEach(function () {
    $this->user    = actingAsUser('admin');
    $this->service = app(SalesService::class);
    $this->tenantId = $this->user->tenant_id ?? 1;
});

// ─── Orders ────────────────────────────────────────────────────────────────────

test('test_can_create_sales_order', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [
            [
                'description' => 'Product A',
                'quantity'    => 5,
                'unit_price'  => 10000,
            ],
        ],
    ]);

    expect($order)->toBeInstanceOf(SalesOrder::class)
        ->and($order->status)->toBe('draft')
        ->and($order->currency)->toBe('XOF')
        ->and($order->reference)->toStartWith('SO-')
        ->and($order->lines)->toHaveCount(1);

    $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'draft']);
});

test('test_can_confirm_order', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [
            ['description' => 'Item B', 'quantity' => 2, 'unit_price' => 5000],
        ],
    ]);

    expect($order->status)->toBe('draft');

    $confirmed = $this->service->confirmOrder($order);

    expect($confirmed->status)->toBe('confirmed')
        ->and($confirmed->confirmed_at)->not->toBeNull();

    $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'confirmed']);
});

test('test_cannot_confirm_already_confirmed_order', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [
            ['description' => 'Item C', 'quantity' => 1, 'unit_price' => 1000],
        ],
    ]);

    $this->service->confirmOrder($order);

    expect(fn () => $this->service->confirmOrder($order->fresh()))
        ->toThrow(\RuntimeException::class);
});

test('test_order_total_calculated_correctly', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [
            [
                'description'      => 'Product X',
                'quantity'         => 10,
                'unit_price'       => 1000,    // base: 10 000
                'discount_percent' => 10,      // discount: 1 000 → after: 9 000
                'tax_rate'         => 20,      // tax: 1 800 → total: 10 800
            ],
            [
                'description' => 'Product Y',
                'quantity'    => 2,
                'unit_price'  => 500,          // base: 1 000, no discount, no tax → 1 000
            ],
        ],
    ]);

    // Line 1: 10*1000=10000, discount=1000, tax=(10000-1000)*0.2=1800, total=10800
    // Line 2: 2*500=1000, no discount, no tax, total=1000
    // Order: subtotal=11000, discount=1000, tax=1800, total=11800
    expect((float) $order->subtotal)->toBe(11000.0)
        ->and((float) $order->discount_amount)->toBe(1000.0)
        ->and((float) $order->tax_amount)->toBe(1800.0)
        ->and((float) $order->total)->toBe(11800.0);
});

// ─── Quotations ────────────────────────────────────────────────────────────────

test('test_can_create_quotation', function () {
    $quotation = $this->service->createQuotation([
        'tenant_id'   => $this->tenantId,
        'currency'    => 'XOF',
        'total'       => 75000,
        'valid_until' => now()->addDays(30)->toDateString(),
        'created_by'  => $this->user->id,
    ]);

    expect($quotation)->toBeInstanceOf(SalesQuotation::class)
        ->and($quotation->status)->toBe('draft')
        ->and($quotation->reference)->toStartWith('QT-')
        ->and((float) $quotation->total)->toBe(75000.0);

    $this->assertDatabaseHas('sales_quotations', ['id' => $quotation->id, 'status' => 'draft']);
});

test('test_can_convert_quotation_to_order', function () {
    $quotation = $this->service->createQuotation([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'total'      => 50000,
        'created_by' => $this->user->id,
    ]);

    $order = $this->service->convertQuotationToOrder($quotation);

    expect($order)->toBeInstanceOf(SalesOrder::class)
        ->and($order->currency)->toBe('XOF');

    $this->assertDatabaseHas('sales_quotations', [
        'id'                    => $quotation->id,
        'status'                => 'accepted',
        'converted_to_order_id' => $order->id,
    ]);
});

test('test_cannot_convert_already_converted_quotation', function () {
    $quotation = $this->service->createQuotation([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'total'      => 10000,
        'created_by' => $this->user->id,
    ]);

    $this->service->convertQuotationToOrder($quotation);

    expect(fn () => $this->service->convertQuotationToOrder($quotation->fresh()))
        ->toThrow(\RuntimeException::class);
});

// ─── API ───────────────────────────────────────────────────────────────────────

test('test_api_can_list_orders', function () {
    // Create a couple of orders first
    $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/orders');

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);
});

test('test_api_can_create_order_via_endpoint', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales/orders', [
            'currency' => 'XOF',
            'lines'    => [
                ['description' => 'Test Item', 'quantity' => 3, 'unit_price' => 2000],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['status' => 'draft'])
        ->assertJsonStructure(['id', 'reference', 'status', 'total', 'lines']);
});

test('test_api_can_cancel_order', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [['description' => 'To cancel', 'quantity' => 1, 'unit_price' => 500]],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/sales/orders/{$order->id}/cancel", [
            'reason' => 'Customer withdrew',
        ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['status' => 'cancelled']);
});

// ─── Additional Tests ──────────────────────────────────────────────────────────

test('create order with zero lines results in subtotal zero', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [],
    ]);

    expect((float) $order->subtotal)->toBe(0.0)
        ->and((float) $order->total)->toBe(0.0)
        ->and($order->lines)->toHaveCount(0);
});

test('add line with discount_percent recalculates totals correctly', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [
            [
                'description'      => 'Discounted Item',
                'quantity'         => 4,
                'unit_price'       => 5000,
                'discount_percent' => 25,
            ],
        ],
    ]);

    // subtotal = 4*5000 = 20000, discount = 25% of 20000 = 5000, total = 15000
    expect((float) $order->subtotal)->toBe(20000.0)
        ->and((float) $order->discount_amount)->toBe(5000.0)
        ->and((float) $order->total)->toBe(15000.0);
});

test('confirm order twice throws RuntimeException', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [
            ['description' => 'Item D', 'quantity' => 1, 'unit_price' => 1000],
        ],
    ]);

    $this->service->confirmOrder($order);

    expect(fn () => $this->service->confirmOrder($order->fresh()))
        ->toThrow(\RuntimeException::class);
});

test('can cancel a confirmed order', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [['description' => 'Item E', 'quantity' => 1, 'unit_price' => 2000]],
    ]);

    $confirmed = $this->service->confirmOrder($order);
    $cancelled = $this->service->cancelOrder($confirmed, 'Customer changed mind');

    expect($cancelled->status)->toBe('cancelled')
        ->and($cancelled->cancelled_at)->not->toBeNull();

    $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'cancelled']);
});

test('cancel already cancelled order throws RuntimeException', function () {
    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [['description' => 'Item F', 'quantity' => 1, 'unit_price' => 500]],
    ]);

    $this->service->cancelOrder($order, 'First cancel');

    expect(fn () => $this->service->cancelOrder($order->fresh(), 'Second cancel'))
        ->toThrow(\RuntimeException::class);
});

test('quotation with valid_until in past is expired', function () {
    $quotation = $this->service->createQuotation([
        'tenant_id'   => $this->tenantId,
        'currency'    => 'XOF',
        'total'       => 30000,
        'valid_until' => now()->subDays(5)->toDateString(),
        'created_by'  => $this->user->id,
    ]);

    expect($quotation->isExpired())->toBeTrue();
});

test('convert expired quotation still works since isConvertible only checks status', function () {
    $quotation = $this->service->createQuotation([
        'tenant_id'   => $this->tenantId,
        'currency'    => 'XOF',
        'total'       => 20000,
        'valid_until' => now()->subDays(1)->toDateString(),
        'created_by'  => $this->user->id,
    ]);

    // isExpired() is true but isConvertible() only checks status — still draft so convertible
    expect($quotation->isExpired())->toBeTrue()
        ->and($quotation->isConvertible())->toBeTrue();
});

test('convert already converted quotation throws RuntimeException', function () {
    $quotation = $this->service->createQuotation([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'total'      => 15000,
        'created_by' => $this->user->id,
    ]);

    // First conversion succeeds
    $this->service->convertQuotationToOrder($quotation);

    // Second conversion must fail
    expect(fn () => $this->service->convertQuotationToOrder($quotation->fresh()))
        ->toThrow(\RuntimeException::class);
});

test('order reference is auto-generated in SO-YYYY-NNNNN format', function () {
    $year = now()->format('Y');

    $order = $this->service->createOrder([
        'tenant_id'  => $this->tenantId,
        'currency'   => 'XOF',
        'created_by' => $this->user->id,
        'lines'      => [['description' => 'Item G', 'quantity' => 1, 'unit_price' => 100]],
    ]);

    expect($order->reference)->toMatch('/^SO-' . $year . '-\d{5}$/');
});

test('orders list endpoint supports pagination', function () {
    // Create 3 orders
    for ($i = 0; $i < 3; $i++) {
        $this->service->createOrder([
            'tenant_id'  => $this->tenantId,
            'currency'   => 'XOF',
            'created_by' => $this->user->id,
            'lines'      => [['description' => "Paged Item {$i}", 'quantity' => 1, 'unit_price' => 1000]],
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/orders?per_page=2&page=1');

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
});
