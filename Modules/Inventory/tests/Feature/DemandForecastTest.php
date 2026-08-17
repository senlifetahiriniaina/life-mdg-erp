<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SeasonalFactor;


beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('employee');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

// ── DemandForecast CRUD ───────────────────────────────────────────────────────

it('can list demand forecasts', function () {
    $product = Product::factory()->create();
    DemandForecast::factory()->count(3)->create(['product_id' => $product->id]);

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/demand-forecasts')
        ->assertOk()
        ->assertJsonPath('total', 3);
});

it('can filter demand forecasts by product', function () {
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();
    DemandForecast::factory()->count(2)->create(['product_id' => $p1->id]);
    DemandForecast::factory()->count(4)->create(['product_id' => $p2->id]);

    $this->withToken($this->token)
        ->getJson("/api/v1/inventory/demand-forecasts?product_id={$p1->id}")
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('can create a demand forecast manually', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts', [
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'forecasted_qty' => 120.5,
            'method' => 'moving_average',
        ])
        ->assertCreated()
        ->assertJsonPath('product_id', $product->id)
        ->assertJsonPath('forecasted_qty', 120.5)
        ->assertJsonPath('status', 'draft');
});

it('can show a demand forecast', function () {
    $forecast = DemandForecast::factory()->create();

    $this->withToken($this->token)
        ->getJson("/api/v1/inventory/demand-forecasts/{$forecast->id}")
        ->assertOk()
        ->assertJsonPath('id', $forecast->id);
});

it('can update a forecast to confirmed status', function () {
    $forecast = DemandForecast::factory()->create(['status' => 'draft']);

    $this->withToken($this->token)
        ->putJson("/api/v1/inventory/demand-forecasts/{$forecast->id}", [
            'status' => 'confirmed',
            'forecasted_qty' => 200.0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonFragment(['forecasted_qty' => 200]);
});

it('can delete a demand forecast', function () {
    $forecast = DemandForecast::factory()->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/inventory/demand-forecasts/{$forecast->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('inventory_demand_forecasts', ['id' => $forecast->id]);
});

// ── Generate endpoint ─────────────────────────────────────────────────────────

it('can generate forecasts for a product with no history', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/generate', [
            'product_id' => $product->id,
            'months' => 3,
            'method' => 'moving_average',
        ])
        ->assertOk()
        ->assertJsonPath('count', 3)
        ->assertJsonStructure(['count', 'forecasts' => [['id', 'forecasted_qty', 'period_start', 'period_end', 'method']]]);
});

it('generates different months with non-overlapping periods', function () {
    $product = Product::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/generate', [
            'product_id' => $product->id,
            'months' => 2,
        ])
        ->assertOk();

    $forecasts = collect($response->json('forecasts'));
    expect($forecasts->count())->toBe(2);
    expect($forecasts[0]['period_start'])->not->toBe($forecasts[1]['period_start']);
});

it('re-generates forecasts for existing periods using updateOrCreate', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/generate', [
            'product_id' => $product->id,
            'months' => 1,
        ])
        ->assertOk();

    $countBefore = DemandForecast::where('product_id', $product->id)->count();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/generate', [
            'product_id' => $product->id,
            'months' => 1,
        ])
        ->assertOk();

    expect(DemandForecast::where('product_id', $product->id)->count())->toBe($countBefore);
});

it('validates generate requires a valid product_id', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/generate', ['product_id' => 99999])
        ->assertUnprocessable();
});

it('validates generate months range', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/generate', [
            'product_id' => $product->id,
            'months' => 25,
        ])
        ->assertUnprocessable();
});

// ── Reconcile endpoint ────────────────────────────────────────────────────────

it('returns 404 when reconciling a non-existent forecast', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/reconcile', [
            'product_id' => $product->id,
            'month' => Carbon::now()->subMonth()->format('Y-m'),
        ])
        ->assertNotFound();
});

it('marks an existing forecast as expired on reconcile', function () {
    $product = Product::factory()->create();
    $lastMonth = Carbon::now()->subMonth()->startOfMonth();

    $forecast = DemandForecast::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => null,
        'period_start' => $lastMonth,
        'period_end' => $lastMonth->copy()->endOfMonth(),
        'status' => 'confirmed',
    ]);

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/demand-forecasts/reconcile', [
            'product_id' => $product->id,
            'month' => $lastMonth->format('Y-m'),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'expired');
});

// ── Seasonal factors ──────────────────────────────────────────────────────────

it('can list seasonal factors', function () {
    $product = Product::factory()->create();
    SeasonalFactor::factory()->count(4)->create(['product_id' => $product->id]);

    $this->withToken($this->token)
        ->getJson("/api/v1/inventory/seasonal-factors?product_id={$product->id}")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('can create a seasonal factor', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/seasonal-factors', [
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period_index' => 12,
            'factor' => 1.45,
            'notes' => 'December peak',
        ])
        ->assertSuccessful()
        ->assertJsonPath('factor', 1.45)
        ->assertJsonPath('period_index', 12);
});

it('upserts a seasonal factor on duplicate key', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/seasonal-factors', [
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period_index' => 6,
            'factor' => 1.10,
        ])
        ->assertSuccessful();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/seasonal-factors', [
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period_index' => 6,
            'factor' => 1.25,
        ])
        ->assertOk()
        ->assertJsonPath('factor', 1.25);

    expect(SeasonalFactor::where('product_id', $product->id)->count())->toBe(1);
});

it('can delete a seasonal factor', function () {
    $factor = SeasonalFactor::factory()->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/inventory/seasonal-factors/{$factor->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('inventory_seasonal_factors', ['id' => $factor->id]);
});

it('validates factor must be between 0.01 and 10', function () {
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/seasonal-factors', [
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period_index' => 1,
            'factor' => 0,
        ])
        ->assertUnprocessable();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/seasonal-factors', [
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period_index' => 1,
            'factor' => 11,
        ])
        ->assertUnprocessable();
});

// ── Model unit tests ──────────────────────────────────────────────────────────

it('DemandForecast::accuracy() returns null when actual_qty is null', function () {
    $forecast = DemandForecast::factory()->make(['actual_qty' => null]);
    expect($forecast->accuracy())->toBeNull();
});

it('DemandForecast::accuracy() calculates correctly', function () {
    $forecast = DemandForecast::factory()->make([
        'forecasted_qty' => 100.0,
        'actual_qty' => 90.0,
    ]);
    expect($forecast->accuracy())->toBe(90.0); // 10% error → 90% accuracy
});

it('DemandForecast::accuracy() returns 100 for perfect forecast', function () {
    $forecast = DemandForecast::factory()->make([
        'forecasted_qty' => 50.0,
        'actual_qty' => 50.0,
    ]);
    expect($forecast->accuracy())->toBe(100.0);
});
