<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\PickLine;
use Modules\Inventory\Models\Product;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list picking waves', function () {
    PickingWave::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/waves')
        ->assertOk()
        ->assertJsonPath('total', 3);
});

it('can create a picking wave', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/waves', [
            'order_ids' => [101, 102, 103],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'open');
});

it('can start a wave', function () {
    $wave = PickingWave::factory()->open()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/waves/{$wave->id}/start")
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');
});

it('can pick a line on a wave', function () {
    $product = Product::factory()->create();
    $wave = PickingWave::factory()->inProgress()->create();
    $line = PickLine::factory()->create([
        'wave_id' => $wave->id,
        'product_id' => $product->id,
        'qty_requested' => 10,
        'qty_picked' => 0,
        'status' => 'pending',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/waves/{$wave->id}/lines/{$line->id}/pick", ['qty' => 10])
        ->assertOk()
        ->assertJsonPath('status', 'picked');
});

it('can complete a wave', function () {
    $wave = PickingWave::factory()->inProgress()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/waves/{$wave->id}/complete")
        ->assertOk()
        ->assertJsonPath('status', 'completed');
});
