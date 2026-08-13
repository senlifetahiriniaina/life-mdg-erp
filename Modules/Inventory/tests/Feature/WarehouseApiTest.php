<?php

use Modules\Inventory\Models\Warehouse;

describe('Warehouse API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list warehouses', function () {
        Warehouse::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/warehouses');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('can create a warehouse', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/warehouses', [
                'name' => 'Main Warehouse',
                'type' => 'main',
                'address' => '123 Main St',
                'city' => 'New York',
                'country' => 'US',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Main Warehouse');

        $this->assertDatabaseHas('inventory_warehouses', ['name' => 'Main Warehouse']);
    });

    test('can search warehouses by location', function () {
        Warehouse::factory()->create(['city' => 'New York']);
        Warehouse::factory()->count(2)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/warehouses?search=New');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    test('can get a single warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/warehouses/{$warehouse->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $warehouse->id);
    });

    test('can update a warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/inventory/warehouses/{$warehouse->id}", [
                'city' => 'Los Angeles',
                'type' => 'transit',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inventory_warehouses', [
            'id' => $warehouse->id,
            'city' => 'Los Angeles',
            'type' => 'transit',
        ]);
    });

    test('can delete a warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('inventory_warehouses', ['id' => $warehouse->id]);
    });
});
