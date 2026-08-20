<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\ProductionOrder;

uses(RefreshDatabase::class);

// Chantier 23 (volet C de la feuille de route Chantier 21) — commande de
// production simplifiée : suivi du statut d'un article entre "matières
// réunies" et "livré", en passant par la sous-traitance de production
// réelle de Life MDG. Locks in the real HTTP routes, the linear status
// pipeline, and RBAC.

test('the production orders web page renders the real Inertia component', function () {
    actingAsUser('purchasing-manager');

    // component(..., false) skips inertia-laravel's own page-exists finder
    // — same established workaround as CostingSheetTest (Chantier 21).
    $this->get('/inventory/production-orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Inventory/ProductionOrders/Index', false));
});

test('a purchasing manager can create a production order', function () {
    actingAsUser('purchasing-manager');

    $response = $this->postJson('/api/v1/inventory/production-orders', [
        'quantity' => 500,
        'notes' => 'Commande test',
    ]);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('draft');
    expect($response->json('data.reference'))->toStartWith('PRD-');
    expect(ProductionOrder::count())->toBe(1);
});

test('the status pipeline only advances one step at a time', function () {
    actingAsUser('purchasing-manager');
    $order = ProductionOrder::factory()->create(['status' => 'draft']);

    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'materials_ready'])
        ->assertOk()
        ->assertJsonPath('data.status', 'materials_ready');

    // Skipping directly to 'delivered' is rejected.
    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'delivered'])
        ->assertStatus(422);

    $order->refresh();
    expect($order->status)->toBe('materials_ready');
});

test('reaching in_subcontracting stamps started_at, and delivered stamps delivered_at', function () {
    actingAsUser('purchasing-manager');
    $order = ProductionOrder::factory()->create(['status' => 'materials_ready']);

    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'in_subcontracting'])->assertOk();
    $order->refresh();
    expect($order->started_at)->not->toBeNull();

    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'quality_check'])->assertOk();
    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'ready_for_delivery'])->assertOk();
    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'delivered'])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('delivered');
    expect($order->delivered_at)->not->toBeNull();
});

test('cancelling is allowed from any non-terminal status', function () {
    actingAsUser('purchasing-manager');
    $order = ProductionOrder::factory()->create(['status' => 'quality_check']);

    $this->postJson("/api/v1/inventory/production-orders/{$order->id}/transition", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('a terminal order (delivered or cancelled) rejects any further transition', function () {
    actingAsUser('purchasing-manager');
    $delivered = ProductionOrder::factory()->create(['status' => 'delivered']);
    $cancelled = ProductionOrder::factory()->create(['status' => 'cancelled']);

    $this->postJson("/api/v1/inventory/production-orders/{$delivered->id}/transition", ['status' => 'materials_ready'])
        ->assertStatus(422);
    $this->postJson("/api/v1/inventory/production-orders/{$cancelled->id}/transition", ['status' => 'materials_ready'])
        ->assertStatus(422);
});

test('a sales-rep (no inventory access) cannot reach production orders', function () {
    actingAsUser('sales-rep');

    $this->getJson('/api/v1/inventory/production-orders')->assertForbidden();
});

test('an unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/inventory/production-orders')->assertUnauthorized();
});

test('deleting a production order soft-deletes it (excluded from listing/find)', function () {
    actingAsUser('purchasing-manager');
    $order = ProductionOrder::factory()->create();

    $this->deleteJson("/api/v1/inventory/production-orders/{$order->id}")->assertNoContent();

    expect(ProductionOrder::find($order->id))->toBeNull();
    expect(ProductionOrder::withTrashed()->find($order->id))->not->toBeNull();
});
