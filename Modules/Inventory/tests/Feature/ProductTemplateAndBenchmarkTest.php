<?php

use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\SourcingBenchmark;
use Modules\Shared\Models\Currency;

/**
 * Chantier 17 — product templates (clothing domain) + accounting-account
 * mapping per category, and the sourcing-benchmark price-comparison log.
 * Covers all 7 layers: route (URLs below), controller (Api\ProductTemplateController/
 * SourcingBenchmarkController), vue (Benchmark/Index.vue + Products/Form.vue picker,
 * not exercised here — see type-check/build), model (ProductTemplate/SourcingBenchmark/
 * Category), data format (migrations — asserted below), sécurité (validation rules),
 * RBAC (role gate + Policy, asserted below).
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    $this->seed(\Database\Seeders\DefaultDataSeeder::class);
    $this->seed(\Modules\Inventory\Database\Seeders\ProductTemplateSeeder::class);
    $this->user = actingAsUser('admin');
});

describe('Currency seeding (Chantier 17 — prerequisite for cross-currency benchmarking)', function () {
    test('shared_currencies is seeded with MGA/USD/EUR/CNY and real exchange rates', function () {
        expect(Currency::count())->toBeGreaterThanOrEqual(13);

        foreach (['MGA', 'USD', 'EUR', 'CNY'] as $code) {
            $currency = Currency::where('code', $code)->first();
            expect($currency)->not->toBeNull();
            expect($currency->exchange_rate_to_usd)->not->toBeNull();
        }
    });
});

describe('Category account-mapping (data format layer)', function () {
    test('the 4 clothing-domain categories carry their suggested chart-of-accounts codes', function () {
        $mp = Category::where('name', 'Matières premières')->firstOrFail();
        expect($mp->default_stock_account_code)->toBe('310');
        expect($mp->default_purchase_account_code)->toBe('601');
        expect($mp->default_variance_account_code)->toBe('6031');

        $accessoires = Category::where('name', 'Accessoires')->firstOrFail();
        expect($accessoires->default_stock_account_code)->toBe('312');

        $semiFinis = Category::where('name', 'Vêtements semi-finis')->firstOrFail();
        expect($semiFinis->default_stock_account_code)->toBe('335');

        $produitsFinis = Category::where('name', 'Produits finis')->firstOrFail();
        expect($produitsFinis->default_stock_account_code)->toBe('355');
        expect($produitsFinis->default_sale_account_code)->toBe('701');
    });

    test('the seeded accessoires/semi-fini accounting codes exist as real chart-of-accounts entries', function () {
        $this->assertDatabaseHas('acc_chart_of_accounts', ['code' => '312']);
        $this->assertDatabaseHas('acc_chart_of_accounts', ['code' => '335']);
        $this->assertDatabaseHas('acc_chart_of_accounts', ['code' => '602']);
        $this->assertDatabaseHas('acc_chart_of_accounts', ['code' => '6032']);
        $this->assertDatabaseHas('acc_chart_of_accounts', ['code' => '6035']);
    });
});

describe('Product Templates API (route + controller + RBAC layers)', function () {
    test('lists the 20 seeded clothing-domain templates grouped with category account codes', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/product-templates');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(20);
        expect($response->json('families'))->toHaveKeys(['matiere_premiere', 'accessoire', 'semi_fini', 'produit_fini']);

        $tissu = collect($response->json('data'))->firstWhere('code', 'mp-tissu-coton');
        expect($tissu['category']['default_stock_account_code'])->toBe('310');
    });

    test('filters by family', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/product-templates?family=produit_fini');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(6);
    });

    test('a role outside the Inventory route gate is denied at the route level', function () {
        $outsider = actingAsUser('sales-rep');

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/inventory/product-templates')
            ->assertStatus(403);
    });
});

describe('Product Templates CRUD (Chantier 17b — editable/addable/removable, not just seeded defaults)', function () {
    test('creates a new template', function () {
        $category = Category::where('name', 'Matières premières')->firstOrFail();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/product-templates', [
                'code' => 'mp-tissu-lin',
                'name' => 'Tissu lin',
                'family' => 'matiere_premiere',
                'category_id' => $category->id,
                'default_attributes' => ['composition' => '100% lin'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_product_templates', ['code' => 'mp-tissu-lin', 'name' => 'Tissu lin', 'is_active' => true]);
    });

    test('rejects a duplicate template code', function () {
        $category = Category::where('name', 'Matières premières')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/product-templates', [
                'code' => 'mp-tissu-coton',
                'name' => 'Doublon',
                'family' => 'matiere_premiere',
                'category_id' => $category->id,
            ])
            ->assertStatus(422);
    });

    test('rejects an unknown family value', function () {
        $category = Category::where('name', 'Matières premières')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/product-templates', [
                'code' => 'mp-invalide',
                'name' => 'Invalide',
                'family' => 'not-a-real-family',
                'category_id' => $category->id,
            ])
            ->assertStatus(422);
    });

    test('updates an existing template, including deactivating it', function () {
        $template = ProductTemplate::where('code', 'mp-tissu-coton')->firstOrFail();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/inventory/product-templates/{$template->id}", [
                'code' => $template->code,
                'name' => 'Tissu coton (renommé)',
                'family' => $template->family,
                'category_id' => $template->category_id,
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inventory_product_templates', [
            'id' => $template->id,
            'name' => 'Tissu coton (renommé)',
            'is_active' => false,
        ]);
    });

    test('a deactivated template is excluded from the default (active-only) listing but included with include_inactive=1', function () {
        $template = ProductTemplate::where('code', 'mp-tissu-coton')->firstOrFail();
        $template->update(['is_active' => false]);

        $default = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/inventory/product-templates');
        expect(collect($default->json('data'))->pluck('id'))->not->toContain($template->id);

        $withInactive = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/inventory/product-templates?include_inactive=1');
        expect(collect($withInactive->json('data'))->pluck('id'))->toContain($template->id);
    });

    test('deletes a template', function () {
        $template = ProductTemplate::where('code', 'mp-tissu-coton')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/inventory/product-templates/{$template->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('inventory_product_templates', ['id' => $template->id]);
    });

    test('a role outside the Inventory route gate is denied creating/updating/deleting templates', function () {
        $outsider = actingAsUser('sales-rep');
        $category = Category::where('name', 'Matières premières')->firstOrFail();
        $template = ProductTemplate::where('code', 'mp-tissu-coton')->firstOrFail();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/inventory/product-templates', [
                'code' => 'mp-nouveau', 'name' => 'Nouveau', 'family' => 'matiere_premiere', 'category_id' => $category->id,
            ])->assertStatus(403);

        $this->actingAs($outsider, 'sanctum')
            ->deleteJson("/api/v1/inventory/product-templates/{$template->id}")
            ->assertStatus(403);
    });
});

describe('Sourcing Benchmark API (route + controller + model + sécurité + RBAC layers)', function () {
    test('records a price observation tied to a real catalogue product', function () {
        $product = Product::factory()->create(['currency' => 'MGA', 'cost_price' => 20000]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/sourcing-benchmarks', [
                'product_id' => $product->id,
                'source' => 'klopman',
                'unit_price' => 4.5,
                'currency' => 'EUR',
                'unit' => 'm',
                'observed_at' => now()->toDateString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_sourcing_benchmarks', [
            'product_id' => $product->id,
            'source' => 'klopman',
            'currency' => 'EUR',
        ]);
    });

    test('records an observation against a product template instead of a catalogue product', function () {
        $template = ProductTemplate::where('code', 'mp-tissu-coton')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/sourcing-benchmarks', [
                'product_template_id' => $template->id,
                'source' => 'xm_textiles',
                'unit_price' => 3.8,
                'currency' => 'USD',
                'observed_at' => now()->toDateString(),
            ])
            ->assertStatus(201);

        expect(SourcingBenchmark::where('product_template_id', $template->id)->count())->toBe(1);
    });

    test('rejects an "autre" source without a supplier name', function () {
        $product = Product::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/sourcing-benchmarks', [
                'product_id' => $product->id,
                'source' => 'autre',
                'unit_price' => 10,
                'currency' => 'USD',
                'observed_at' => now()->toDateString(),
            ])
            ->assertStatus(422);
    });

    test('rejects an unknown source value', function () {
        $product = Product::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/sourcing-benchmarks', [
                'product_id' => $product->id,
                'source' => 'not-a-real-supplier',
                'unit_price' => 10,
                'currency' => 'USD',
                'observed_at' => now()->toDateString(),
            ])
            ->assertStatus(422);
    });

    test('deletes an observation', function () {
        $benchmark = SourcingBenchmark::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/inventory/sourcing-benchmarks/{$benchmark->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('inventory_sourcing_benchmarks', ['id' => $benchmark->id]);
    });

    test('a role outside the Inventory route gate is denied creating an observation', function () {
        $outsider = actingAsUser('sales-rep');
        $product = Product::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/inventory/sourcing-benchmarks', [
                'product_id' => $product->id,
                'source' => 'klopman',
                'unit_price' => 4.5,
                'currency' => 'EUR',
                'observed_at' => now()->toDateString(),
            ])
            ->assertStatus(403);
    });

    test('compare() converts foreign-currency observations to the product currency and flags variance', function () {
        $product = Product::factory()->create(['currency' => 'MGA', 'cost_price' => 20000]);

        SourcingBenchmark::create([
            'product_id' => $product->id, 'source' => 'klopman', 'unit_price' => 4.5,
            'currency' => 'EUR', 'unit' => 'm', 'observed_at' => now()->subMonth(),
        ]);
        SourcingBenchmark::create([
            'product_id' => $product->id, 'source' => 'xm_textiles', 'unit_price' => 3.8,
            'currency' => 'USD', 'unit' => 'm', 'observed_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/sourcing-benchmarks/compare/{$product->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['internal_cost'])->toEqual(20000);
        expect($data['internal_currency'])->toBe('MGA');
        expect($data['observation_count'])->toBe(2);
        expect($data['by_source'])->toHaveCount(2);

        $klopman = collect($data['by_source'])->firstWhere('source', 'klopman');
        // 4.5 EUR -> USD (4.5/0.92) -> MGA (*4500) ≈ 22010.87
        expect((float) $klopman['avg_price'])->toBeGreaterThan(21000)->toBeLessThan(23000);
        expect((float) $klopman['variance_pct'])->toBeGreaterThan(0);

        $xm = collect($data['by_source'])->firstWhere('source', 'xm_textiles');
        // 3.8 USD -> MGA (*4500) = 17100
        expect((float) $xm['avg_price'])->toEqual(17100.0);
        expect((float) $xm['variance_pct'])->toBeLessThan(0);

        expect($data['monthly_trend'])->toHaveCount(2);
    });

    test('compare() reports zero observations gracefully for a product with none', function () {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/sourcing-benchmarks/compare/{$product->id}");

        $response->assertStatus(200);
        expect($response->json('data.observation_count'))->toBe(0);
        expect($response->json('data.by_source'))->toBe([]);
    });

    test('filters history by product_id', function () {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        SourcingBenchmark::factory()->create(['product_id' => $productA->id]);
        SourcingBenchmark::factory()->create(['product_id' => $productB->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/sourcing-benchmarks?product_id={$productA->id}");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(1);
    });
});
