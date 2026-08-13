<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Modules\Inventory\Jobs\SyncInventoryToEcommerceJob;
use Modules\Inventory\Models\Product;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    Queue::fake();
});

it('can sync a single product to ecommerce', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/sync/ecommerce/product/{$product->id}")
        ->assertOk()
        ->assertJsonStructure(['message', 'ecommerce_synced_at']);
});

it('can queue a full sync to ecommerce', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/sync/ecommerce/all')
        ->assertOk()
        ->assertJsonStructure(['message', 'queued_at']);

    Queue::assertPushed(SyncInventoryToEcommerceJob::class);
});

it('can get ecommerce sync status', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/sync/ecommerce/status')
        ->assertOk()
        ->assertJsonStructure(['last_sync_at', 'pending_count']);
});

it('unauthenticated user cannot trigger sync', function () {
    $product = Product::factory()->create();
    $this->postJson("/api/v1/inventory/sync/ecommerce/product/{$product->id}")->assertUnauthorized();
});
