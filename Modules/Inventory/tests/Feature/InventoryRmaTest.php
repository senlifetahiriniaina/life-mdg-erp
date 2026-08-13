<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\Rma;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list RMAs', function () {
    Rma::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/rmas')
        ->assertOk()
        ->assertJsonPath('total', 3);
});

it('can create an RMA', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/rmas', [
            'customer_name' => 'Jean Dupont',
            'reason' => 'Produit défectueux',
            'items' => [['product_id' => 1, 'qty' => 2, 'price' => 49.99]],
            'return_method' => 'refund',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'requested')
        ->assertJsonPath('customer_name', 'Jean Dupont');
});

it('can approve an RMA', function () {
    $rma = Rma::factory()->requested()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/rmas/{$rma->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', 'approved');
});

it('can receive an RMA', function () {
    $rma = Rma::factory()->approved()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/rmas/{$rma->id}/receive")
        ->assertOk()
        ->assertJsonPath('status', 'received');
});

it('can process a refund for an RMA', function () {
    $rma = Rma::factory()->create(['status' => 'received']);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/rmas/{$rma->id}/refund")
        ->assertOk()
        ->assertJsonPath('status', 'refunded');
});
