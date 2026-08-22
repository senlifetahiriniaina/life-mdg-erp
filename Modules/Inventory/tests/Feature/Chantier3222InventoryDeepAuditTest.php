<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;

/**
 * Chantier 32.22 — 14-layer deep audit of Modules\Inventory.
 *
 * Layer 6 (sécurité approfondie): this module had zero company/tenant
 * scoping on any of its resources despite already being flagged (Chantier
 * 19) as a known gap — confirmed empirically here with 2 real companies
 * and real HTTP requests, then fixed for the 6 highest-value resources
 * (products, categories, warehouses, suppliers, stock movements, purchase
 * orders).
 */
function inventoryOtherCompanyUser(int $companyId, string $role = 'inventory-analyst'): User
{
    $user = User::factory()->create(['company_id' => $companyId]);
    $user->assignRole($role);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();

    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'Inventory', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

describe('Chantier 32.22 — company/tenant isolation', function () {
    beforeEach(function () {
        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
        $this->userA = actingAsUser('inventory-analyst');
        $this->userA->update(['company_id' => $this->companyA->id]);
    });

    test('products are isolated per company', function () {
        $productA = Product::factory()->create(['name' => 'Prod-A-secret', 'company_id' => $this->companyA->id]);

        $userB = inventoryOtherCompanyUser($this->companyB->id);
        $this->actingAs($userB, 'sanctum');
        $productB = Product::factory()->create(['name' => 'Prod-B', 'company_id' => $this->companyB->id]);

        $index = $this->getJson('/api/v1/inventory/products');
        $names = collect($index->json('data'))->pluck('name')->all();
        expect($names)->toContain('Prod-B')->not->toContain('Prod-A-secret');

        $this->getJson("/api/v1/inventory/products/{$productA->id}")->assertStatus(404);
        $this->getJson("/api/v1/inventory/products/{$productB->id}")->assertStatus(200);
    });

    test('warehouses are isolated per company', function () {
        $whA = Warehouse::factory()->create(['name' => 'Wh-A-secret', 'company_id' => $this->companyA->id]);

        $userB = inventoryOtherCompanyUser($this->companyB->id);
        $this->actingAs($userB, 'sanctum');
        Warehouse::factory()->create(['name' => 'Wh-B', 'company_id' => $this->companyB->id]);

        $index = $this->getJson('/api/v1/inventory/warehouses');
        $names = collect($index->json('data'))->pluck('name')->all();
        expect($names)->not->toContain('Wh-A-secret');

        $this->getJson("/api/v1/inventory/warehouses/{$whA->id}")->assertStatus(404);
    });

    test('suppliers are isolated per company', function () {
        $supA = Supplier::factory()->create(['name' => 'Sup-A-secret', 'company_id' => $this->companyA->id]);

        $userB = inventoryOtherCompanyUser($this->companyB->id);
        $this->actingAs($userB, 'sanctum');
        Supplier::factory()->create(['name' => 'Sup-B', 'company_id' => $this->companyB->id]);

        $index = $this->getJson('/api/v1/inventory/suppliers');
        $names = collect($index->json('data'))->pluck('name')->all();
        expect($names)->not->toContain('Sup-A-secret');

        $this->getJson("/api/v1/inventory/suppliers/{$supA->id}")->assertStatus(404);
        $this->putJson("/api/v1/inventory/suppliers/{$supA->id}", ['name' => 'Hacked'])->assertStatus(404);
        $this->deleteJson("/api/v1/inventory/suppliers/{$supA->id}")->assertStatus(404);
    });

    test('categories are isolated per company', function () {
        $catA = Category::factory()->create(['name' => 'Cat-A-secret', 'company_id' => $this->companyA->id]);

        $userB = inventoryOtherCompanyUser($this->companyB->id);
        $this->actingAs($userB, 'sanctum');

        $this->getJson("/api/v1/inventory/categories/{$catA->id}")->assertStatus(404);
    });

    test('purchase orders are isolated per company', function () {
        $supA = Supplier::factory()->create(['company_id' => $this->companyA->id]);
        $poA = PurchaseOrder::factory()->create(['supplier_id' => $supA->id, 'company_id' => $this->companyA->id, 'status' => 'draft']);

        $userB = inventoryOtherCompanyUser($this->companyB->id, 'purchasing-manager');
        $this->actingAs($userB, 'sanctum');

        $this->getJson("/api/v1/inventory/purchase-orders/{$poA->id}")->assertStatus(404);
        $this->postJson("/api/v1/inventory/purchase-orders/{$poA->id}/send")->assertStatus(404);
        $this->deleteJson("/api/v1/inventory/purchase-orders/{$poA->id}")->assertStatus(404);
    });

    test('stock movements are isolated per company', function () {
        $productA = Product::factory()->create(['company_id' => $this->companyA->id]);
        $whA = Warehouse::factory()->create(['company_id' => $this->companyA->id]);
        $movement = app(InventoryService::class)->recordMovement([
            'product_id' => $productA->id,
            'warehouse_id' => $whA->id,
            'type' => 'in',
            'quantity' => 5,
            'company_id' => $this->companyA->id,
        ]);

        $userB = inventoryOtherCompanyUser($this->companyB->id);
        $this->actingAs($userB, 'sanctum');

        $this->getJson("/api/v1/inventory/stock-movements/{$movement->id}")->assertStatus(404);
        $index = $this->getJson('/api/v1/inventory/stock-movements');
        $ids = collect($index->json('data'))->pluck('id')->all();
        expect($ids)->not->toContain($movement->id);
    });

    test('same-company user can still see their own data (not fail-closed for everyone)', function () {
        $product = Product::factory()->create(['name' => 'MyProd', 'company_id' => $this->companyA->id]);

        $index = $this->getJson('/api/v1/inventory/products');
        $names = collect($index->json('data'))->pluck('name')->all();
        expect($names)->toContain('MyProd');

        $this->getJson("/api/v1/inventory/products/{$product->id}")->assertStatus(200);
    });
});
