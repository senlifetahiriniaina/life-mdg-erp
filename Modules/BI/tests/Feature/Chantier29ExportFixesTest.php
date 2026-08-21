<?php

declare(strict_types=1);

use Modules\Accounting\Models\Invoice;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\HR\Models\Employee;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;

// Chantier 29 — 7-layer verification of the reporting/PDF/XLSX export
// machinery found (and fixed) two concrete bugs:
//
// 1. `ExportController::download()` loads `bi::exports.{$dataset}` for 6
//    datasets (invoices/leads/opportunities/employees/products/
//    stock_movements) but only `exports/report.blade.php` existed on disk —
//    every one of these 6 real, routed PDF exports threw a Blade
//    "View not found" exception. Fixed by adding the 5 missing views.
// 2. `ExportService::exportWidgetCsv()` wrote only a header row and closed
//    the stream — a widget CSV export always downloaded an empty body.
//    Fixed to write real data rows: real per-row data from the widget's own
//    `config['rows']` when present, otherwise a genuine summary row built
//    from the widget's own real stored attributes.
it('exports every real dataset as a genuine PDF, not a Blade "view not found" error', function (string $dataset) {
    $user = actingAsUser('manager');

    // Seed one real row for the dataset under test so the PDF has real
    // content to render (an empty dataset already worked before the fix —
    // it's the *presence* of the view file that was missing).
    match ($dataset) {
        'invoices' => Invoice::factory()->create(),
        'leads' => Lead::factory()->create(),
        'opportunities' => Opportunity::factory()->create(),
        'employees' => Employee::factory()->create(['user_id' => $user->id]),
        'products' => Product::factory()->create(),
        'stock_movements' => StockMovement::factory()->create(),
    };

    $response = $this->get("/api/v1/bi/export/{$dataset}?format=pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('pdf');

    // A real DomPDF stream always starts with the literal PDF file-header
    // magic bytes — a Blade "view not found" exception never reaches this
    // point (it 500s before DomPDF ever runs), so this is a genuine
    // end-to-end confirmation the view was found, compiled, and rendered.
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
})->with([
    'invoices',
    'leads',
    'opportunities',
    'employees',
    'products',
    'stock_movements',
]);

it('exports a dataset PDF correctly even with zero rows (the pre-fix always-empty case)', function () {
    actingAsUser('manager');

    $response = $this->get('/api/v1/bi/export/invoices?format=pdf');

    $response->assertOk();
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('exports a widget CSV with real per-row data when the widget config carries rows', function () {
    $user = actingAsUser('manager');

    $dashboard = Dashboard::create([
        'user_id' => $user->id,
        'name' => 'Test Dashboard',
        'is_public' => false,
    ]);

    $widget = Widget::create([
        'dashboard_id' => $dashboard->id,
        'title' => 'Ventes par région',
        'type' => 'data_table',
        'config' => [
            'columns' => ['region', 'total'],
            'rows' => [
                ['region' => 'Antananarivo', 'total' => 12000],
                ['region' => 'Toamasina', 'total' => 8000],
            ],
        ],
        'refresh_interval' => 300,
    ]);

    $response = $this->get("/api/v1/bi/widgets/{$widget->id}/export?format=csv");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $csv))));

    // Header row + 2 real data rows — not just a header row and nothing else.
    expect($lines)->toHaveCount(3);
    expect($lines[0])->toBe('region,total');
    expect($lines[1])->toBe('Antananarivo,12000');
    expect($lines[2])->toBe('Toamasina,8000');
});

it('exports a widget CSV with a real summary row when no per-row data source exists', function () {
    $user = actingAsUser('manager');

    $dashboard = Dashboard::create([
        'user_id' => $user->id,
        'name' => 'Test Dashboard',
        'is_public' => false,
    ]);

    $widget = Widget::create([
        'dashboard_id' => $dashboard->id,
        'title' => 'KPI Widget',
        'type' => 'kpi_card',
        'config' => null,
        'refresh_interval' => 300,
    ]);

    $response = $this->get("/api/v1/bi/widgets/{$widget->id}/export?format=csv");

    $response->assertOk();
    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $csv))));

    // Header row + exactly one real data row built from the widget's own
    // stored attributes — never just a header row with nothing underneath.
    expect($lines)->toHaveCount(2);
    expect($lines[0])->toContain('id');
    expect($lines[0])->toContain('title');
    // The data row carries the widget's real id/title, not placeholders.
    expect($lines[1])->toContain((string) $widget->id);
    expect($lines[1])->toContain('KPI Widget');
});

it('unauthenticated user cannot access the fixed dataset exports', function () {
    $this->get('/api/v1/bi/export/invoices?format=pdf')
        ->assertRedirect();
});
