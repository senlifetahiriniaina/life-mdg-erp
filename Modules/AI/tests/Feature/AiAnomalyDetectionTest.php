<?php

declare(strict_types=1);

// Replaces Modules/Analytics/tests/Feature/AnomalyDetectionTest.php, which targeted
// an orphaned duplicate anomaly-detection model registry in the Analytics module
// (isolation_forest/LOF/Mahalanobis config CRUD + detected-anomaly triage) that had
// zero real callers anywhere in the codebase besides its own tests, and whose schema
// never matched its own Eloquent model ($fillable used `model_name`, the migration
// declared a NOT NULL `name` column nothing ever wrote to).
//
// The ERP's real, working, routed anomaly detection is Modules\AI's
// AiAnomalyDetectionService / AiAnomalyController — it inspects real business data
// (Inventory low-stock, Accounting overdue invoices, HR missing payslips) and is
// exposed at POST/GET /api/v1/ai/anomalies* and DELETE /api/v1/ai/anomalies/{id}.
// This file exercises that real, routed API instead.

// Chantier 8.6 (AI): AiAnomalyController::resolveTenantId() now reads the
// user's real `company_id` (dropping the client-controlled X-Tenant-Id header
// fallback that was the actual cross-tenant leak — see CLAUDE.md), and
// AiAnomalyDetectionService now queries the real `inventory_products` /
// `inventory_stock` tables (the old `products`/`stock_quantity` names never
// existed, so every call silently fell into the service's own catch block and
// returned one canned demo anomaly per module regardless of real data). These
// tests now seed a real understocked product for the acting user's company_id
// instead of relying on that removed fallback demo data.
function actingAsAdminWithCompany(): \App\Models\User
{
    $user = actingAsUser('admin');
    $company = \App\Models\Company::factory()->create();
    $user->forceFill(['company_id' => $company->id])->save();

    return $user;
}

function seedLowStockAnomaly(int $companyId): void
{
    $warehouseId = \Illuminate\Support\Facades\DB::table('inventory_warehouses')->insertGetId([
        'name' => 'Anomaly Test Warehouse', 'code' => 'ANOM-WH', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $productId = \Illuminate\Support\Facades\DB::table('inventory_products')->insertGetId([
        'name' => 'Anomaly Test Product', 'sku' => 'ANOM-' . uniqid(), 'tenant_id' => $companyId,
        'is_active' => true, 'reorder_point' => 10, 'reorder_qty' => 5,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \Illuminate\Support\Facades\DB::table('inventory_stock')->insert([
        'product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('lists active anomalies across modules for the current tenant', function () {
    $user = actingAsAdminWithCompany();
    seedLowStockAnomaly($user->company_id);

    $response = $this->getJson('/api/v1/ai/anomalies');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'module', 'type', 'severity', 'title', 'description', 'detected_at', 'ai_powered']],
            'count',
        ]);
});

it('detect endpoint returns the same active anomalies as the index endpoint', function () {
    $user = actingAsAdminWithCompany();
    seedLowStockAnomaly($user->company_id);

    $detect = $this->postJson('/api/v1/ai/anomalies/detect');
    $index = $this->getJson('/api/v1/ai/anomalies');

    $detect->assertOk();
    $index->assertOk();

    expect($detect->json('count'))->toBe($index->json('count'));
});

it('anomalies are sorted with critical severity first', function () {
    actingAsUser('admin');

    $response = $this->getJson('/api/v1/ai/anomalies');
    $response->assertOk();

    $severities = array_column($response->json('data'), 'severity');
    $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
    $ranks = array_map(fn (string $s) => $order[$s] ?? 9, $severities);

    $sorted = $ranks;
    sort($sorted);

    expect($ranks)->toBe($sorted);
});

it('dismissing an anomaly removes it from the active list', function () {
    $user = actingAsAdminWithCompany();
    seedLowStockAnomaly($user->company_id);

    $before = $this->getJson('/api/v1/ai/anomalies')->assertOk();
    $anomalies = $before->json('data');

    expect($anomalies)->not->toBeEmpty();

    $anomalyId = $anomalies[0]['id'];

    $this->deleteJson("/api/v1/ai/anomalies/{$anomalyId}")
        ->assertOk()
        ->assertJsonPath('message', 'Anomaly dismissed.');

    $after = $this->getJson('/api/v1/ai/anomalies')->assertOk();

    expect(collect($after->json('data'))->pluck('id'))->not->toContain($anomalyId);
});

it('requires authentication to list anomalies', function () {
    $this->getJson('/api/v1/ai/anomalies')->assertUnauthorized();
});
