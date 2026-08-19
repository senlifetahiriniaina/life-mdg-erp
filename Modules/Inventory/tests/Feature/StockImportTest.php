<?php

use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 16 — stock import (entrée/sortie) avec création automatique des
 * produits inconnus dans le catalogue. Mirrors TreasuryImportTest's shape:
 * a mix of real file-upload preview/commit round trips plus regression
 * coverage for the StockMovementController::store() fix found along the way.
 */
function makeCsvUpload(string $content, string $name = 'import.csv'): \Illuminate\Http\Testing\File
{
    return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $content);
}

describe('Stock Import', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->warehouse = Warehouse::factory()->create();
    });

    describe('Preview', function () {
        test('resolves an existing product by SKU and flags an unknown one as new', function () {
            $product = Product::factory()->create(['sku' => 'EXIST-01', 'name' => 'Produit existant']);
            Stock::create(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 10]);

            $csv = "sku;nom;quantite;type\nEXIST-01;Produit existant;5;entree\n;Produit tout nouveau;3;sortie\n";
            $file = makeCsvUpload($csv);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/preview', ['file' => $file]);

            $response->assertStatus(200);
            $rows = $response->json('rows');
            expect($rows)->toHaveCount(2);

            $existingRow = collect($rows)->firstWhere('sku', 'EXIST-01');
            expect($existingRow['product_exists'])->toBeTrue();
            expect($existingRow['matched_product_id'])->toBe($product->id);
            expect($existingRow['type'])->toBe('in');

            $newRow = collect($rows)->firstWhere('name', 'Produit tout nouveau');
            expect($newRow['product_exists'])->toBeFalse();
            expect($newRow['matched_product_id'])->toBeNull();
            expect($newRow['type'])->toBe('out');
        });

        test('rejects a file with no usable product/quantity columns', function () {
            $csv = "colonne_inutile\nvaleur\n";
            $file = makeCsvUpload($csv);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/preview', ['file' => $file]);

            $response->assertStatus(422);
        });
    });

    describe('Commit', function () {
        test('auto-creates a new product with Marchandises/Pièce defaults and a generated SKU', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/commit', [
                    'warehouse_id' => $this->warehouse->id,
                    'rows' => [
                        ['sku' => null, 'name' => 'Sac de riz 25kg', 'quantity' => 40, 'type' => 'in', 'unit_cost' => 15000],
                    ],
                ]);

            $response->assertStatus(201);
            $created = $response->json('data.products_created');
            expect($created)->toHaveCount(1);
            expect($created[0]['name'])->toBe('Sac de riz 25kg');

            $product = Product::find($created[0]['id']);
            expect($product)->not->toBeNull();
            // `category`/`unit` are separate plain-string display columns from the
            // category_id/unit_id FKs on inventory_products (ProductResource reads
            // $this->category directly) — check both, not just the FK. Product has
            // no unit() relation at all (confirmed via grep — only category() exists),
            // so the unit is only ever checked via the FK + plain-string column here.
            expect($product->category)->toBe('Marchandises');
            expect($product->unit)->toBe('Pièce');
            expect($product->category()->first()->name)->toBe('Marchandises');

            $unit = Unit::find($product->unit_id);
            expect($unit->symbol)->toBe('pc');
            expect($product->cost_price)->toEqual(15000);
        });

        test('reuses the same Marchandises category and Pièce unit already seeded by DefaultDataSeeder', function () {
            $category = Category::firstOrCreate(['name' => 'Marchandises']);
            $unit = Unit::firstOrCreate(['name' => 'Pièce'], ['symbol' => 'pc', 'type' => 'unit']);

            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/commit', [
                    'warehouse_id' => $this->warehouse->id,
                    'rows' => [
                        ['sku' => null, 'name' => 'Produit générique', 'quantity' => 1, 'type' => 'in', 'unit_cost' => null],
                    ],
                ])->assertStatus(201);

            expect(Category::where('name', 'Marchandises')->count())->toBe(1);
            expect(Unit::where('name', 'Pièce')->count())->toBe(1);

            $product = Product::where('name', 'Produit générique')->firstOrFail();
            expect($product->category_id)->toBe($category->id);
            expect($product->unit_id)->toBe($unit->id);
        });

        test('updates real on-hand stock quantity for an "in" movement', function () {
            $product = Product::factory()->create(['sku' => 'STK-01']);
            Stock::create(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 20]);

            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/commit', [
                    'warehouse_id' => $this->warehouse->id,
                    'rows' => [
                        ['sku' => 'STK-01', 'name' => $product->name, 'quantity' => 15, 'type' => 'in', 'unit_cost' => null],
                    ],
                ])->assertStatus(201);

            $stock = Stock::where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
            expect((float) $stock->quantity)->toBe(35.0);
        });

        test('updates real on-hand stock quantity for an "out" movement', function () {
            $product = Product::factory()->create(['sku' => 'STK-02']);
            Stock::create(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 50]);

            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/commit', [
                    'warehouse_id' => $this->warehouse->id,
                    'rows' => [
                        ['sku' => 'STK-02', 'name' => $product->name, 'quantity' => 20, 'type' => 'out', 'unit_cost' => null],
                    ],
                ])->assertStatus(201);

            $stock = Stock::where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
            expect((float) $stock->quantity)->toBe(30.0);
        });

        test('rejects an out movement that would drive stock negative, and rolls back the whole batch', function () {
            $product = Product::factory()->create(['sku' => 'STK-03']);
            Stock::create(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/commit', [
                    'warehouse_id' => $this->warehouse->id,
                    'rows' => [
                        ['sku' => 'STK-03', 'name' => $product->name, 'quantity' => 100, 'type' => 'out', 'unit_cost' => null],
                    ],
                ]);

            $response->assertStatus(422);

            $stock = Stock::where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
            expect((float) $stock->quantity)->toBe(5.0);
            expect(StockMovement::where('product_id', $product->id)->count())->toBe(0);
        });

        test('rejects an unknown movement type', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-imports/commit', [
                    'warehouse_id' => $this->warehouse->id,
                    'rows' => [
                        ['sku' => null, 'name' => 'Produit X', 'quantity' => 1, 'type' => 'transfer', 'unit_cost' => null],
                    ],
                ]);

            $response->assertStatus(422);
        });
    });
});

describe('Chantier 16 — StockMovementController::store() regression', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('posting a stock movement through the real endpoint actually updates on-hand quantity', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        Stock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/stock-movements', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'in',
                'quantity' => 25,
                'reason' => 'Réception fournisseur',
            ])->assertStatus(201);

        $stock = Stock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->quantity)->toBe(35.0);
    });
});
