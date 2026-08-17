<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Models\Product;


beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('employee');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list cross-dock operations', function () {
    CrossdockOperation::factory()->count(2)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/crossdock')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('can plan a cross-dock operation', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/crossdock', [
            'product_id' => $product->id,
            'qty' => 15.5,
            'inbound_shipment_id' => 1,
            'outbound_order_id' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'planned');
});

it('can execute a cross-dock operation', function () {
    $op = CrossdockOperation::factory()->planned()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/crossdock/{$op->id}/execute")
        ->assertOk()
        ->assertJsonPath('status', 'executed');
});
