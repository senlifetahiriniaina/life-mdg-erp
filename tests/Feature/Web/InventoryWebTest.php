<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\Product;

uses(RefreshDatabase::class);

/**
 * /inventory/products is gated by module:Inventory + role:employee,...
 * (since Chantier 8.3il) — a bare unroled User::factory() user now
 * correctly 403s. Same seed-guard pattern used throughout
 * Modules/Inventory/tests for this exact bug class.
 */
function inventoryWebTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('guest is redirected to login from products index', function () {
    $this->get('/inventory/products')->assertRedirect('/login');
});

test('authenticated user sees products index', function () {
    $user = inventoryWebTestUser();
    Product::create([
        'name'       => 'Test Product',
        'sku'        => 'SKU-001',
        'sale_price' => 10.00,
        'cost_price' => 5.00,
    ]);

    $this->actingAs($user)
        ->get('/inventory/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Products/Index')
            ->has('products')
            ->has('filters')
        );
});

test('products index returns paginated products', function () {
    $user = inventoryWebTestUser();
    Product::create(['name' => 'Widget A', 'sku' => 'SKU-001', 'sale_price' => 10.00, 'cost_price' => 5.00]);
    Product::create(['name' => 'Widget B', 'sku' => 'SKU-002', 'sale_price' => 20.00, 'cost_price' => 8.00]);

    $this->actingAs($user)
        ->get('/inventory/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Products/Index')
            ->has('products.data', 2)
        );
});

test('search filter returns matching products', function () {
    $user = inventoryWebTestUser();
    Product::create(['name' => 'Unique Widget', 'sku' => 'SKU-100', 'sale_price' => 10.00, 'cost_price' => 5.00]);
    Product::create(['name' => 'Other Item',   'sku' => 'SKU-200', 'sale_price' => 15.00, 'cost_price' => 7.00]);

    $this->actingAs($user)
        ->get('/inventory/products?search=Unique')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Products/Index')
            ->has('products.data', 1)
        );
});

test('search filter by sku returns matching products', function () {
    $user = inventoryWebTestUser();
    Product::create(['name' => 'Product One', 'sku' => 'FIND-001', 'sale_price' => 10.00, 'cost_price' => 5.00]);
    Product::create(['name' => 'Product Two', 'sku' => 'SKIP-002', 'sale_price' => 10.00, 'cost_price' => 5.00]);

    $this->actingAs($user)
        ->get('/inventory/products?search=FIND')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Products/Index')
            ->has('products.data', 1)
        );
});
