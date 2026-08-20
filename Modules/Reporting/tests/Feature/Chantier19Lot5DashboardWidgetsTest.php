<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reporting\Models\Dashboard;
use Modules\Reporting\Models\ReportWidget;

uses(RefreshDatabase::class);

/**
 * Chantier 19 (Lot 5): DashboardService's module resolvers
 * (resolveInventoryData/resolveAccountingData/resolveHrData/resolveCrmData)
 * all targeted table names that either never existed anywhere in this repo
 * (`invoices`, `supplier_invoices`, `employees`, `leave_requests`, `leads`)
 * or existed but with a completely different column set (`products`, the
 * catch-all-scaffold table, not Inventory's real `inventory_products`) —
 * confirmed empirically (before the fix) that every one of these widgets
 * silently returned {error: 'Données temporairement indisponibles'} via
 * resolveDataSource()'s own catch block, invisible in every prior
 * code-reading-only audit including Chantier 8.5ars, which only fixed the
 * *tenant-scoping* symptom on this same file. Sales' resolver turned out to
 * have the identical bug class independently (`order_date`/`total_amount`/
 * `order_id`/`total_price` — none of them real columns; real: `confirmed_at`/
 * `total`/`sales_order_id`/`line_total`), found while writing this test's
 * own fixture, plus a MySQL-only `MONTH()` call that would have fatally
 * errored under this app's real sqlite driver even with the columns fixed.
 * Dashboard/widget *creation* itself was separately, fully broken too:
 * Dashboard::$fillable wrote `owner_id`, a column that has never existed
 * (the real column is `created_by`) — fixed in Dashboard.php/DashboardService.php/
 * ReportingController.php, not re-tested exhaustively here since the fixture
 * helper below (`widgetFor()`) already exercises the real `Dashboard::create()`
 * path on every single test in this file.
 */
function widgetUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');
    test()->actingAs($user, 'sanctum');
    return $user;
}

function widgetFor(User $user, string $module, string $query): ReportWidget
{
    $dashboard = Dashboard::create([
        'tenant_id'  => $user->company_id,
        'name'       => 'Test Dashboard ' . uniqid(),
        'created_by' => $user->id,
    ]);

    return ReportWidget::create([
        'tenant_id'    => $user->company_id,
        'dashboard_id' => $dashboard->id,
        'widget_type'  => 'kpi',
        'title'        => "{$module}/{$query}",
        'data_source'  => ['module' => $module, 'query' => $query],
        'position_x'   => 0,
        'position_y'   => 0,
        'width'        => 1,
        'height'       => 1,
    ]);
}

test('sales monthly revenue widget is repointed to the real sales_orders columns and works on sqlite', function () {
    $user = widgetUser();

    \DB::table('sales_orders')->insert([
        'tenant_id' => $user->company_id, 'reference' => 'SO-1', 'status' => 'confirmed', 'created_by' => $user->id,
        'confirmed_at' => now(), 'total' => 150000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $widget = widgetFor($user, 'Sales', 'kpi_revenue_month');
    $resp = $this->getJson("/api/v1/reporting/widgets/{$widget->id}/data");

    $resp->assertOk();
    expect($resp->json('data.error'))->toBeNull();
    expect((float) $resp->json('data.value'))->toBe(150000.0);
});

test('sales monthly_revenue widget does not use a MySQL-only MONTH() call and groups correctly on sqlite', function () {
    $user = widgetUser();

    \DB::table('sales_orders')->insert([
        'tenant_id' => $user->company_id, 'reference' => 'SO-2', 'status' => 'confirmed', 'created_by' => $user->id,
        'confirmed_at' => now(), 'total' => 100,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $widget = widgetFor($user, 'Sales', 'monthly_revenue');
    $resp = $this->getJson("/api/v1/reporting/widgets/{$widget->id}/data");

    $resp->assertOk();
    $rows = $resp->json('data');
    expect($rows)->toHaveCount(1);
    expect((int) $rows[0]['month'])->toBe((int) now()->format('n'));
    expect((float) $rows[0]['revenue'])->toBe(100.0);
});

test('inventory widgets are repointed to the real inventory_products/inventory_stock tables', function () {
    $user = widgetUser();

    $warehouse = \Modules\Inventory\Models\Warehouse::factory()->create();
    $productId = \DB::table('inventory_products')->insertGetId([
        'tenant_id' => $user->company_id, 'name' => 'Produit A', 'sku' => 'PA-1',
        'cost_price' => 1000, 'reorder_point' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \DB::table('inventory_stock')->insert([
        'product_id' => $productId, 'warehouse_id' => $warehouse->id, 'quantity' => 5,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $stockValue = widgetFor($user, 'Inventory', 'kpi_stock_value');
    $resp = $this->getJson("/api/v1/reporting/widgets/{$stockValue->id}/data");
    $resp->assertOk();
    expect($resp->json('data.error'))->toBeNull();
    expect((float) $resp->json('data.value'))->toBe(5000.0); // 5 * 1000

    $lowStock = widgetFor($user, 'Inventory', 'low_stock');
    $resp2 = $this->getJson("/api/v1/reporting/widgets/{$lowStock->id}/data");
    $resp2->assertOk();
    expect($resp2->json('data'))->toHaveCount(1);
    expect($resp2->json('data.0.name'))->toBe('Produit A');
});

test('accounting receivables widget is repointed to the real acc_invoices table', function () {
    $user = widgetUser();

    \DB::table('acc_invoices')->insert([
        'number' => 'INV-1', 'type' => 'invoice', 'status' => 'sent',
        'currency' => 'MGA', 'total' => 80000, 'amount_paid' => 30000,
        'invoice_date' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $widget = widgetFor($user, 'Accounting', 'kpi_outstanding_receivables');
    $resp = $this->getJson("/api/v1/reporting/widgets/{$widget->id}/data");

    $resp->assertOk();
    expect($resp->json('data.error'))->toBeNull();
    expect((float) $resp->json('data.value'))->toBe(50000.0); // 80000 - 30000
});

test('hr headcount widget is repointed to the real hr_employees table', function () {
    $user = widgetUser();
    \Modules\HR\Models\Employee::factory()->create(['status' => 'active']);

    $widget = widgetFor($user, 'HR', 'kpi_headcount');
    $resp = $this->getJson("/api/v1/reporting/widgets/{$widget->id}/data");

    $resp->assertOk();
    expect($resp->json('data.error'))->toBeNull();
    expect((int) $resp->json('data.value'))->toBeGreaterThanOrEqual(1);
});

test('crm active leads widget is repointed to the real crm_leads table, scoped by company_id', function () {
    $user = widgetUser();

    \DB::table('crm_leads')->insert([
        'company_id' => $user->company_id, 'first_name' => 'Jean', 'last_name' => 'R',
        'status' => 'new', 'created_at' => now(), 'updated_at' => now(),
    ]);
    // another company's lead must not be counted
    \DB::table('crm_leads')->insert([
        'company_id' => $user->company_id + 999, 'first_name' => 'Autre', 'last_name' => 'Co',
        'status' => 'new', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $widget = widgetFor($user, 'CRM', 'kpi_active_leads');
    $resp = $this->getJson("/api/v1/reporting/widgets/{$widget->id}/data");

    $resp->assertOk();
    expect($resp->json('data.error'))->toBeNull();
    expect((int) $resp->json('data.value'))->toBe(1);
});

test('creating a dashboard via the real POST endpoint no longer fails on the owner_id/title schema mismatch', function () {
    $user = widgetUser();

    // Bare branch (no template/industry): Dashboard::create() with 'created_by'.
    $resp = $this->postJson('/api/v1/reporting/dashboards', [
        'name' => 'Mon tableau de bord',
    ]);
    $resp->assertCreated();
    expect($resp->json('created_by'))->toBe($user->id);

    // Industry branch: createDefaultDashboard() + its widget-template loop —
    // this is where the report_widgets title/data_source/position_x/etc.
    // schema mismatch fatally errored on every real call before the fix.
    $resp2 = $this->postJson('/api/v1/reporting/dashboards', [
        'name'     => 'Dashboard Commerce',
        'industry' => 'commerce',
    ]);
    $resp2->assertCreated();
    expect($resp2->json('widgets'))->not->toBeEmpty();
    expect($resp2->json('widgets.0.title'))->not->toBeNull();

    // Template branch: cloneTemplate() + its own widget-template loop.
    $resp3 = $this->postJson('/api/v1/reporting/dashboards', [
        'name'     => 'Dashboard Exécutif',
        'template' => 'executive',
    ]);
    $resp3->assertCreated();
    expect($resp3->json('widgets'))->not->toBeEmpty();
});
