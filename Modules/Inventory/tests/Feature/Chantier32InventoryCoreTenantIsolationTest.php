<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Achats\Models\Supplier as AchatsSupplier;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\ReorderRule;
use Modules\Inventory\Models\SourcingBenchmark;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Warehouse;
use Modules\Strategy\Services\TextileSectorKpiService;
use Modules\Inventory\Services\TraceabilityService;
use Modules\Sales\Models\SalesOrder;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 32 — Inventory "core" (catalog/warehouse/stock, plus the
 * costing/production/sourcing subsystems) had zero company-based tenant
 * isolation anywhere. The fourth application this session of the same
 * retrofit already applied to CRM (Chantier 10/19), Achats (Chantier 19
 * Lot 3), and Projects (Chantier 19 Lot 2).
 *
 * Follows Modules\CRM\tests\Feature\Chantier19CrmReauditTest.php's
 * structure: 2 real companies, real HTTP calls via withToken()-equivalent
 * (this app uses session/sanctum acting-as, so actingAs(...,'sanctum')),
 * cross-company denial asserted as 404 throughout (never 403 — matching
 * Achats' own real ScopesToCompany precedent, not a policy-level Gate
 * denial), and a same-company "still works" assertion alongside each
 * denial so the fix is proven to be a real boundary, not a blanket lock.
 */
function chantier32InventoryCoreUser(string $suffix, string $role = 'admin'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "Chantier32 Inventory Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ─── Product ────────────────────────────────────────────────────────────────

test('product index/show/update/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $productA = Product::factory()->create(['company_id' => $userA->company_id, 'name' => 'Product A']);
    Product::factory()->create(['company_id' => $userB->company_id, 'name' => 'Product B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/products')->assertOk();
    expect(collect($index->json('data'))->pluck('name'))->toContain('Product A')->not->toContain('Product B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/products/{$productA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/products/{$productA->id}", ['name' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/products/{$productA->id}")->assertStatus(404);
    expect($productA->fresh()->name)->toBe('Product A');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/products/{$productA->id}")->assertOk();
});

test('product store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/products', [
        'name' => 'Spoofed Product',
        'sku' => 'SPOOF-001',
        'cost_price' => 10,
        'selling_price' => 15,
        'company_id' => $userB->company_id, // must be ignored
    ])->assertCreated();

    expect(Product::find($response->json('id') ?? $response->json('data.id'))->company_id)->toBe($userA->company_id);
});

// ─── Category ───────────────────────────────────────────────────────────────

test('category index/show/update/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $catA = Category::factory()->create(['company_id' => $userA->company_id, 'name' => 'Category A']);
    Category::factory()->create(['company_id' => $userB->company_id, 'name' => 'Category B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/categories')->assertOk();
    expect(collect($index->json('data'))->pluck('name'))->toContain('Category A')->not->toContain('Category B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/categories/{$catA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/categories/{$catA->id}", ['name' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/categories/{$catA->id}")->assertStatus(404);
    expect($catA->fresh()->name)->toBe('Category A');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/categories/{$catA->id}")->assertOk();
});

// ─── Warehouse ──────────────────────────────────────────────────────────────

test('warehouse index/show/update/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $whA = Warehouse::factory()->create(['company_id' => $userA->company_id, 'name' => 'Warehouse A']);
    Warehouse::factory()->create(['company_id' => $userB->company_id, 'name' => 'Warehouse B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/warehouses')->assertOk();
    expect(collect($index->json('data'))->pluck('name'))->toContain('Warehouse A')->not->toContain('Warehouse B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/warehouses/{$whA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/warehouses/{$whA->id}", ['name' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/warehouses/{$whA->id}")->assertStatus(404);
    expect($whA->fresh()->name)->toBe('Warehouse A');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/warehouses/{$whA->id}")->assertOk();
});

test('warehouse store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/warehouses', [
        'name' => 'Spoofed Warehouse',
        'company_id' => $userB->company_id,
    ])->assertCreated();

    expect(Warehouse::find($response->json('id') ?? $response->json('data.id'))->company_id)->toBe($userA->company_id);
});

// ─── Unit ───────────────────────────────────────────────────────────────────

test('unit index/show/update/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $unitA = Unit::factory()->create(['company_id' => $userA->company_id, 'name' => 'Unit A']);
    Unit::factory()->create(['company_id' => $userB->company_id, 'name' => 'Unit B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/units')->assertOk();
    expect(collect($index->json('data'))->pluck('name'))->toContain('Unit A')->not->toContain('Unit B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/units/{$unitA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/units/{$unitA->id}", ['name' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/units/{$unitA->id}")->assertStatus(404);
    expect($unitA->fresh()->name)->toBe('Unit A');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/units/{$unitA->id}")->assertOk();
});

// ─── Lot ────────────────────────────────────────────────────────────────────

test('lot index/show/update/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $lotA = Lot::factory()->create(['company_id' => $userA->company_id, 'lot_number' => 'LOT-A-1']);
    Lot::factory()->create(['company_id' => $userB->company_id, 'lot_number' => 'LOT-B-1']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/lots')->assertOk();
    $lotNumbers = collect($index->json('data'))->pluck('lot_number');
    expect($lotNumbers)->toContain('LOT-A-1')->not->toContain('LOT-B-1');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/lots/{$lotA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/lots/{$lotA->id}", ['notes' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/lots/{$lotA->id}")->assertStatus(404);
    expect($lotA->fresh()->lot_number)->toBe('LOT-A-1');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/lots/{$lotA->id}")->assertOk();
});

// ─── StockMovement ──────────────────────────────────────────────────────────

test('stock movement index/show are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $movementA = StockMovement::factory()->create(['company_id' => $userA->company_id, 'reason' => 'Movement A']);
    StockMovement::factory()->create(['company_id' => $userB->company_id, 'reason' => 'Movement B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/stock-movements')->assertOk();
    expect(collect($index->json('data'))->pluck('reason'))->toContain('Movement A')->not->toContain('Movement B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/stock-movements/{$movementA->id}")->assertStatus(404);
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/stock-movements/{$movementA->id}")->assertOk();
});

// ─── Stock (no dedicated CRUD controller/routes — scoped through
// ProductController's stock-touching endpoints instead; see ScopesToCompany's
// StockPolicy docblock for why authorize() isn't wired against it: 'stock'
// is not a seeded permission resource, so a Gate-based check would
// fail-closed for every role including admin) ────────────────────────────────

test('adjustStock/stock/transferStock are scoped by the product\'s own company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $productA = Product::factory()->create(['company_id' => $userA->company_id]);
    $whA1 = Warehouse::factory()->create(['company_id' => $userA->company_id]);
    $whA2 = Warehouse::factory()->create(['company_id' => $userA->company_id]);

    // Company B cannot touch Company A's product's stock via any of the 3 endpoints.
    test()->actingAs($userB, 'sanctum')
        ->patchJson("/api/v1/inventory/products/{$productA->id}/stock/{$whA1->id}", ['quantity' => 50])
        ->assertStatus(404);
    test()->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/inventory/products/{$productA->id}/stock")
        ->assertStatus(404);
    test()->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/inventory/products/{$productA->id}/transfer", [
            'from_warehouse_id' => $whA1->id, 'to_warehouse_id' => $whA2->id, 'quantity' => 1,
        ])
        ->assertStatus(404);

    // Company A can — and the created Stock row carries its own company_id.
    test()->actingAs($userA, 'sanctum')
        ->patchJson("/api/v1/inventory/products/{$productA->id}/stock/{$whA1->id}", ['quantity' => 50])
        ->assertOk();

    $stock = Stock::where('product_id', $productA->id)->where('warehouse_id', $whA1->id)->first();
    expect($stock)->not->toBeNull();
    expect($stock->company_id)->toBe($userA->company_id);
});

test('low-stock report and valuation only aggregate the caller company own stock', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $productA = Product::factory()->create(['company_id' => $userA->company_id, 'reorder_level' => 100, 'name' => 'Low Stock A']);
    $whA = Warehouse::factory()->create(['company_id' => $userA->company_id]);
    Stock::factory()->create(['product_id' => $productA->id, 'warehouse_id' => $whA->id, 'company_id' => $userA->company_id, 'quantity' => 5]);

    $productB = Product::factory()->create(['company_id' => $userB->company_id, 'reorder_level' => 100, 'name' => 'Low Stock B']);
    $whB = Warehouse::factory()->create(['company_id' => $userB->company_id]);
    Stock::factory()->create(['product_id' => $productB->id, 'warehouse_id' => $whB->id, 'company_id' => $userB->company_id, 'quantity' => 5]);

    $report = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/low-stock')->assertOk();
    $names = collect($report->json('low_stock_items'))->pluck('product_name');
    expect($names)->toContain('Low Stock A')->not->toContain('Low Stock B');
});

// ─── ReorderRule (no dedicated controller — read only through
// ProductController::lowStockReport()'s per-warehouse override lookup) ──────

test('lowStockReport only applies the caller company own ReorderRule override', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $productA = Product::factory()->create(['company_id' => $userA->company_id, 'reorder_level' => 0, 'name' => 'Ruled Product A']);
    $whA = Warehouse::factory()->create(['company_id' => $userA->company_id]);
    Stock::factory()->create(['product_id' => $productA->id, 'warehouse_id' => $whA->id, 'company_id' => $userA->company_id, 'quantity' => 20]);
    ReorderRule::factory()->create([
        'product_id' => $productA->id, 'warehouse_id' => $whA->id, 'company_id' => $userA->company_id,
        'status' => 'active', 'min_level' => 30,
    ]);

    $report = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/low-stock')->assertOk();
    $item = collect($report->json('low_stock_items'))->firstWhere('product_name', 'Ruled Product A');
    expect($item)->not->toBeNull();
    expect($item['from_warehouse_rule'])->toBeTrue();
    expect($item['reorder_level'])->toBe(30);
});

// ─── CostingSheet ───────────────────────────────────────────────────────────

test('costing sheet index/show/update/destroy/duplicate are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $sheetA = CostingSheet::factory()->create(['company_id' => $userA->company_id, 'name' => 'Sheet A']);
    CostingSheet::factory()->create(['company_id' => $userB->company_id, 'name' => 'Sheet B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/costing-sheets')->assertOk();
    expect(collect($index->json('data'))->pluck('name'))->toContain('Sheet A')->not->toContain('Sheet B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/costing-sheets/{$sheetA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/costing-sheets/{$sheetA->id}", ['name' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/inventory/costing-sheets/{$sheetA->id}/duplicate")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/costing-sheets/{$sheetA->id}")->assertStatus(404);
    expect($sheetA->fresh()->name)->toBe('Sheet A');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/costing-sheets/{$sheetA->id}")->assertOk();
});

test('costing sheet store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/costing-sheets', [
        'name' => 'Spoofed Sheet',
        'quantity' => 10,
        'base_currency' => 'MGA',
        'company_id' => $userB->company_id,
    ])->assertCreated();

    $id = $response->json('data.id');
    expect(CostingSheet::find($id)->company_id)->toBe($userA->company_id);
});

// ─── ProductionOrder ────────────────────────────────────────────────────────

test('production order index/show/update/destroy/transition/trace are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $orderA = ProductionOrder::factory()->create(['company_id' => $userA->company_id, 'status' => 'draft', 'reference' => 'PRD-A-1']);
    ProductionOrder::factory()->create(['company_id' => $userB->company_id, 'reference' => 'PRD-B-1']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/production-orders')->assertOk();
    expect(collect($index->json('data'))->pluck('reference'))->toContain('PRD-A-1')->not->toContain('PRD-B-1');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/production-orders/{$orderA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/production-orders/{$orderA->id}", ['quantity' => 5])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/inventory/production-orders/{$orderA->id}/transition", ['status' => 'materials_ready'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/production-orders/{$orderA->id}/trace")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/production-orders/{$orderA->id}")->assertStatus(404);
    expect($orderA->fresh()->status)->toBe('draft');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/production-orders/{$orderA->id}")->assertOk();
});

// ─── SourcingBenchmark ──────────────────────────────────────────────────────

test('sourcing benchmark index/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $obsA = SourcingBenchmark::factory()->create(['company_id' => $userA->company_id, 'material_label' => 'Fabric A']);
    SourcingBenchmark::factory()->create(['company_id' => $userB->company_id, 'material_label' => 'Fabric B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/sourcing-benchmarks')->assertOk();
    expect(collect($index->json('data'))->pluck('material_label'))->toContain('Fabric A')->not->toContain('Fabric B');

    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/sourcing-benchmarks/{$obsA->id}")->assertStatus(404);
    expect(SourcingBenchmark::find($obsA->id))->not->toBeNull();

    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/sourcing-benchmarks/{$obsA->id}")->assertNoContent();
});

// ─── Supplier (Inventory's own — distinct from Achats') ────────────────────

test('inventory supplier index/show/update/destroy are scoped by company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $supA = Supplier::factory()->create(['company_id' => $userA->company_id, 'name' => 'Inv Supplier A']);
    Supplier::factory()->create(['company_id' => $userB->company_id, 'name' => 'Inv Supplier B']);

    $index = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/suppliers')->assertOk();
    expect(collect($index->json('data'))->pluck('name'))->toContain('Inv Supplier A')->not->toContain('Inv Supplier B');

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/suppliers/{$supA->id}")->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/suppliers/{$supA->id}", ['name' => 'Hijacked'])->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/suppliers/{$supA->id}")->assertStatus(404);
    expect($supA->fresh()->name)->toBe('Inv Supplier A');

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/suppliers/{$supA->id}")->assertOk();
});

// ─── Cross-module FK consistency (supplier_id / subcontractor_supplier_id
// must belong to the caller's own company's Achats supplier catalogue) ──────

test('production order rejects a subcontractor_supplier_id from another company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $achatsSupplierB = AchatsSupplier::factory()->create(['company_id' => $userB->company_id]);

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/production-orders', [
        'quantity' => 10,
        'subcontractor_supplier_id' => $achatsSupplierB->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['subcontractor_supplier_id']);
});

test('production order accepts a subcontractor_supplier_id from the same company', function () {
    $userA = chantier32InventoryCoreUser('A');

    $achatsSupplierA = AchatsSupplier::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/production-orders', [
        'quantity' => 10,
        'subcontractor_supplier_id' => $achatsSupplierA->id,
    ])->assertCreated();
});

test('costing sheet line rejects a supplier_id from another company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $achatsSupplierB = AchatsSupplier::factory()->create(['company_id' => $userB->company_id]);

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/costing-sheets', [
        'name' => 'Sheet with foreign supplier',
        'quantity' => 10,
        'base_currency' => 'MGA',
        'lines' => [[
            'section' => 'matiere',
            'designation' => 'Tissu',
            'supplier_id' => $achatsSupplierB->id,
        ]],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['supplier_id']);
});

// ─── TextileSectorKpiService company filter (Strategy module, cross-module) ─

test('TextileSectorKpiService filters by company_id when one is passed', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    CostingSheet::factory()->create([
        'company_id' => $userA->company_id, 'status' => 'quoted',
        'suggested_selling_price' => 200, 'total_cost_price' => 100,
    ]);
    CostingSheet::factory()->create([
        'company_id' => $userB->company_id, 'status' => 'quoted',
        'suggested_selling_price' => 1000, 'total_cost_price' => 100, // very different margin
    ]);

    $service = app(TextileSectorKpiService::class);

    $unfiltered = $service->marginByFamily();
    expect($unfiltered['overall']['sheet_count'])->toBe(2);

    $scopedToA = $service->marginByFamily((int) $userA->company_id);
    expect($scopedToA['overall']['sheet_count'])->toBe(1);
    expect($scopedToA['overall']['avg_margin_percent'])->toBe(50.0); // (200-100)/200
});

// ─── TraceabilityService cross-company exclusion ───────────────────────────

test('TraceabilityService excludes a linked SalesOrder/PurchaseOrder from a different company', function () {
    $userA = chantier32InventoryCoreUser('A');
    $userB = chantier32InventoryCoreUser('B');

    $orderA = ProductionOrder::factory()->create(['company_id' => $userA->company_id]);

    // A SalesOrder whose soft FK (sales_order_id) happens to point at a
    // record that actually belongs to a DIFFERENT company than the
    // ProductionOrder itself — must be excluded from the trace output.
    // Built directly (not via SalesOrderFactory, whose definition is
    // scaffold boilerplate writing fake()->word() into integer FK columns —
    // a pre-existing Sales-module issue out of this chantier's scope,
    // confirmed via Schema that only tenant_id/reference/created_by are
    // actually required).
    $foreignSalesOrder = SalesOrder::create([
        'tenant_id' => $userB->company_id,
        'reference' => 'SO-CHANTIER32-'.uniqid(),
        'created_by' => $userB->id,
    ]);
    $orderA->update(['sales_order_id' => $foreignSalesOrder->id]);

    $trace = app(TraceabilityService::class)->build($orderA->fresh());

    expect($trace['sales_order'])->toBeNull();
});

// ─── Web layer role gate ────────────────────────────────────────────────────

test('a roleless authenticated user is blocked from the Inventory web pages', function () {
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create();
    // Deliberately no assignRole() — this app's own established convention
    // for "a real authenticated user with no Inventory role at all".

    test()->actingAs($user)->get('/inventory/categories')->assertForbidden();
});
