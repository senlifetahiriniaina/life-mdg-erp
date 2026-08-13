<?php

use Modules\Inventory\Models\Category;

describe('Category API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list categories', function () {
        Category::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/categories');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    });

    test('can create a category', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/categories', [
                'name' => 'Electronics',
                'description' => 'Electronic products',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Electronics');

        $this->assertDatabaseHas('inventory_categories', ['name' => 'Electronics']);
    });

    test('cannot create duplicate category', function () {
        Category::factory()->create(['name' => 'Electronics']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/categories', [
                'name' => 'Electronics',
            ]);

        $response->assertStatus(422);
    });

    test('can get a single category', function () {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id);
    });

    test('can update a category', function () {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/inventory/categories/{$category->id}", [
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inventory_categories', [
            'id' => $category->id,
            'description' => 'Updated description',
        ]);
    });

    test('can delete a category', function () {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/inventory/categories/{$category->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('inventory_categories', ['id' => $category->id]);
    });
});
