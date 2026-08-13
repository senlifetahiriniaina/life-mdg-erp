<?php

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\RedistributionRule;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\TransferOrderLine;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockRedistributionService;

describe('Inventory Stock Redistribution', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(StockRedistributionService::class);
    });

    // ─── Model methods ────────────────────────────────────────────────────────

    test('TransferOrder::canBeApproved() true only for pending_approval', function () {
        $order = TransferOrder::factory()->create(['status' => 'pending_approval']);
        expect($order->canBeApproved())->toBeTrue();

        $order2 = TransferOrder::factory()->create(['status' => 'draft']);
        expect($order2->canBeApproved())->toBeFalse();
    });

    test('TransferOrder::canBeShipped() true only for approved', function () {
        $order = TransferOrder::factory()->create(['status' => 'approved']);
        expect($order->canBeShipped())->toBeTrue();

        $order2 = TransferOrder::factory()->create(['status' => 'draft']);
        expect($order2->canBeShipped())->toBeFalse();
    });

    test('TransferOrder::canBeReceived() true only for in_transit', function () {
        $order = TransferOrder::factory()->create(['status' => 'in_transit']);
        expect($order->canBeReceived())->toBeTrue();
    });

    test('TransferOrder::isOverdue() true when past expected date and not received', function () {
        $order = TransferOrder::factory()->create([
            'status' => 'in_transit',
            'expected_delivery_date' => now()->subDays(3),
            'received_at' => null,
        ]);
        expect($order->isOverdue())->toBeTrue();
    });

    test('TransferOrder::isOverdue() false when received', function () {
        $order = TransferOrder::factory()->create([
            'status' => 'received',
            'expected_delivery_date' => now()->subDays(3),
            'received_at' => now()->subDay(),
        ]);
        expect($order->isOverdue())->toBeFalse();
    });

    test('TransferOrderLine::lineValue() = approved_qty * unit_cost', function () {
        $line = TransferOrderLine::factory()->create([
            'requested_quantity' => 100,
            'approved_quantity' => 80,
            'unit_cost' => 25,
        ]);
        expect($line->lineValue())->toEqual(2000.0);
    });

    test('TransferOrderLine::isFullyReceived() true when received >= shipped', function () {
        $line = TransferOrderLine::factory()->create([
            'shipped_quantity' => 50,
            'received_quantity' => 50,
        ]);
        expect($line->isFullyReceived())->toBeTrue();
    });

    test('RedistributionRule::shouldTrigger() true when stock <= threshold', function () {
        $rule = RedistributionRule::factory()->create(['trigger_threshold' => 100]);
        expect($rule->shouldTrigger(80))->toBeTrue();
        expect($rule->shouldTrigger(100))->toBeTrue();
        expect($rule->shouldTrigger(101))->toBeFalse();
    });

    test('RedistributionRule::isActive() reflects is_active field', function () {
        $rule = RedistributionRule::factory()->create(['is_active' => true]);
        expect($rule->isActive())->toBeTrue();

        $rule2 = RedistributionRule::factory()->create(['is_active' => false]);
        expect($rule2->isActive())->toBeFalse();
    });

    // ─── Service ─────────────────────────────────────────────────────────────

    test('createTransferOrder() creates order with lines and generates reference', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createTransferOrder(
            $from->id,
            $to->id,
            [['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 10]]
        );

        expect($order)->toBeInstanceOf(TransferOrder::class);
        expect($order->reference)->toStartWith('TXFR-');
        expect($order->lines)->toHaveCount(1);
        expect($order->total_items)->toBe(1);
    });

    test('approveTransfer() sets approved status and timestamps', function () {
        $order = TransferOrder::factory()->create(['status' => 'pending_approval']);

        $order = $this->service->approveTransfer($order, $this->user->id);

        expect($order->status)->toBe('approved');
        expect($order->approved_by)->toBe($this->user->id);
        expect($order->approved_at)->not->toBeNull();
    });

    test('shipTransfer() transitions to in_transit and sets shipped_at', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $order = $this->service->createTransferOrder(
            $from->id, $to->id,
            [['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 5]]
        );
        $order->update(['status' => 'approved']);

        $order = $this->service->shipTransfer($order);

        expect($order->status)->toBe('in_transit');
        expect($order->shipped_at)->not->toBeNull();
    });

    test('receiveTransfer() updates quantities and status to received', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $order = $this->service->createTransferOrder(
            $from->id, $to->id,
            [['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 5]]
        );
        $order->update(['status' => 'in_transit']);
        $line = $order->lines->first();
        $line->update(['shipped_quantity' => 50]);

        $order = $this->service->receiveTransfer($order, [$line->id => 48]);

        expect($order->status)->toBe('received');
        expect((float) $order->lines->first()->fresh()->received_quantity)->toEqual(48.0);
    });

    test('cancelTransfer() sets status to cancelled', function () {
        $order = TransferOrder::factory()->create(['status' => 'draft']);
        $order = $this->service->cancelTransfer($order);
        expect($order->status)->toBe('cancelled');
    });

    test('rebalancingAnalysis() returns per-product warehouse data', function () {
        $result = $this->service->rebalancingAnalysis();
        expect($result)->toBeArray();
    });

    test('pendingTransfers() returns in-progress orders', function () {
        TransferOrder::factory()->create(['status' => 'pending_approval']);
        TransferOrder::factory()->create(['status' => 'in_transit']);
        TransferOrder::factory()->create(['status' => 'cancelled']);

        $pending = $this->service->pendingTransfers();

        expect($pending->count())->toBe(2);
    });

    test('autoSuggestTransfers() returns structured result', function () {
        $result = $this->service->autoSuggestTransfers();
        expect($result)->toHaveKeys(['created', 'transfers']);
        expect($result['created'])->toBeInt();
    });

    // ─── API Endpoints ────────────────────────────────────────────────────────

    test('GET /api/v1/inventory/transfer-orders returns 200', function () {
        TransferOrder::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/transfer-orders');

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toHaveCount(3);
    });

    test('POST /api/v1/inventory/transfer-orders creates order', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/transfer-orders', [
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'lines' => [
                    ['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 20],
                ],
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('reference'))->toStartWith('TXFR-');
    });

    test('GET /api/v1/inventory/transfer-orders/{id} shows order with lines', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $createResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/transfer-orders', [
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'lines' => [
                    ['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 20],
                ],
            ]);

        $orderId = $createResponse->json('id');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/transfer-orders/{$orderId}");

        expect($response->status())->toBe(200);
        expect($response->json('lines'))->toHaveCount(1);
    });

    test('POST /api/v1/inventory/transfer-orders/{id}/approve transitions to approved', function () {
        $order = TransferOrder::factory()->create(['status' => 'pending_approval']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/inventory/transfer-orders/{$order->id}/approve");

        expect($response->status())->toBe(200);
        expect($response->json('status'))->toBe('approved');
    });

    test('POST /api/v1/inventory/transfer-orders/{id}/ship transitions to in_transit', function () {
        $order = TransferOrder::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/inventory/transfer-orders/{$order->id}/ship");

        expect($response->status())->toBe(200);
        expect($response->json('status'))->toBe('in_transit');
    });

    test('POST /api/v1/inventory/transfer-orders/{id}/receive transitions to received', function () {
        $order = TransferOrder::factory()->create(['status' => 'in_transit']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/inventory/transfer-orders/{$order->id}/receive");

        expect($response->status())->toBe(200);
        expect($response->json('status'))->toBe('received');
    });

    test('POST /api/v1/inventory/transfer-orders/{id}/cancel cancels order', function () {
        $order = TransferOrder::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/inventory/transfer-orders/{$order->id}/cancel");

        expect($response->status())->toBe(200);
        expect($response->json('status'))->toBe('cancelled');
    });

    test('GET /api/v1/inventory/transfer-orders/pending returns pending orders', function () {
        TransferOrder::factory()->create(['status' => 'pending_approval']);
        TransferOrder::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/transfer-orders/pending');

        expect($response->status())->toBe(200);
    });

    test('GET /api/v1/inventory/redistribution/rules returns rules', function () {
        RedistributionRule::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/redistribution/rules');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveCount(3);
    });

    test('POST /api/v1/inventory/redistribution/rules creates rule', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/redistribution/rules', [
                'name' => 'Low Stock Alert',
                'rule_type' => 'min_stock',
                'trigger_threshold' => 100,
                'transfer_quantity' => 300,
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('name'))->toBe('Low Stock Alert');
    });

    test('POST /api/v1/inventory/redistribution/auto-suggest returns suggestions', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/redistribution/auto-suggest');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['created', 'transfers']);
    });

    test('GET /api/v1/inventory/redistribution/rebalancing-analysis returns analysis', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/redistribution/rebalancing-analysis');

        expect($response->status())->toBe(200);
        expect($response->json())->toBeArray();
    });

    test('GET /api/v1/inventory/warehouses/{id}/transfer-history returns history', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/warehouses/{$warehouse->id}/transfer-history");

        expect($response->status())->toBe(200);
        expect($response->json())->toBeArray();
    });
});

test('unauthenticated user cannot access transfer orders', function () {
    $response = $this->getJson('/api/v1/inventory/transfer-orders');
    expect($response->status())->toBeIn([401, 302]);
});
