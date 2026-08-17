<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;


beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('employee');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can lookup product by barcode', function () {
    $product = Product::factory()->create(['barcode' => '1234567890128']);

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/barcode/product/1234567890128')
        ->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product.barcode', '1234567890128');
});

it('returns 404 for unknown barcode', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/barcode/product/NONEXISTENT999')
        ->assertNotFound();
});

it('can lookup location by barcode code', function () {
    $warehouse = Warehouse::factory()->create();
    $location = Location::factory()->create([
        'warehouse_id' => $warehouse->id,
        'code' => 'A-01-01',
    ]);

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/barcode/location/A-01-01')
        ->assertOk()
        ->assertJsonPath('location.id', $location->id);
});

it('returns 404 for unknown location barcode', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/barcode/location/ZZZ-99-99')
        ->assertNotFound();
});

it('unauthenticated user cannot scan barcodes', function () {
    $this->getJson('/api/v1/inventory/barcode/product/123')->assertUnauthorized();
});
