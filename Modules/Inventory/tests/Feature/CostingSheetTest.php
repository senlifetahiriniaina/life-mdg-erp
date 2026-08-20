<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\CostingSheet;

uses(RefreshDatabase::class);

// Chantier 21 — nomenclature de coût (BOM devis chiffré) : reproduit
// numériquement le chiffrage matière+accessoires+main-d'œuvre+frais fixes
// déjà fait à la main par l'équipe avant-vente. Locks in the real HTTP
// routes, currency conversion, snapshot recalculation, and RBAC.

test('the costing sheets web pages render the real Inertia components', function () {
    actingAsUser('purchasing-manager');

    // component(..., false) skips inertia-laravel's own page-exists finder,
    // which doesn't understand this app's custom app.js module-prefixed
    // resolve() (it looks for "Inventory/CostingSheets/Index.vue" directly
    // under Modules/Inventory/resources/js/Pages/, an extra nesting level
    // that doesn't exist) — same established workaround as every other
    // module-page web test in this app (see Chantier83InventoryOrphanedScreensWebTest).
    $this->get('/inventory/costing-sheets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Inventory/CostingSheets/Index', false));

    $this->get('/inventory/costing-sheets/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Inventory/CostingSheets/Form', false));

    $sheet = CostingSheet::factory()->create();
    $this->get("/inventory/costing-sheets/{$sheet->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory/CostingSheets/Form', false)
            ->where('costingSheetId', $sheet->id)
        );
});

test('a purchasing manager can create a costing sheet with lines and totals are computed', function () {
    actingAsUser('purchasing-manager');

    $response = $this->postJson('/api/v1/inventory/costing-sheets', [
        'name' => 'Pantalon EPI Homme S-XXXL',
        'gender' => 'HOMME',
        'size_range' => 'S-XXXL',
        'quantity' => 1700,
        'base_currency' => 'MGA',
        'production_minutes' => 70,
        'minute_cost' => 120,
        'fixed_cost_coefficient' => 7000,
        'target_margin_percent' => 20,
        'lines' => [
            ['section' => 'matiere', 'designation' => 'Tissu 1 Gris Bleu', 'consumption_qty' => 1.93, 'unit' => 'm', 'unit_price' => 19450, 'currency' => 'MGA'],
            ['section' => 'accessoire_montage', 'designation' => 'Bande retro 5CM', 'consumption_qty' => 2.3, 'unit' => 'm', 'unit_price' => 4323, 'currency' => 'MGA'],
            ['section' => 'valeur_ajoutee', 'designation' => 'PRINT', 'consumption_qty' => 1, 'unit' => 'pc', 'unit_price' => 0, 'currency' => 'MGA', 'margin_percent' => 4],
        ],
    ]);

    $response->assertCreated();
    $sheet = CostingSheet::find($response->json('data.id'));

    expect($sheet->lines()->count())->toBe(3);
    expect((float) $sheet->total_material_cost)->toBeGreaterThan(0);
    expect((float) $sheet->labor_cost)->toBe(8400.0); // 70 * 120
    expect((float) $sheet->total_cost_price)->toBeGreaterThan((float) $sheet->total_material_cost);
    expect((float) $sheet->suggested_selling_price)->toBeGreaterThan((float) $sheet->total_cost_price);
});

test('a line priced in a foreign currency is converted to the sheet base currency', function () {
    // shared_currencies is only seeded by DefaultDataSeeder (Chantier 17
    // precedent, see ProductTemplateAndBenchmarkTest) — not part of the
    // base RolesAndPermissionsSeeder actingAsUser() runs automatically.
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    actingAsUser('purchasing-manager');

    $response = $this->postJson('/api/v1/inventory/costing-sheets', [
        'name' => 'Test devise EUR',
        'quantity' => 100,
        'base_currency' => 'MGA',
        'lines' => [
            ['section' => 'matiere', 'designation' => 'Tissu import EUR', 'consumption_qty' => 1, 'unit' => 'm', 'unit_price' => 4.5, 'currency' => 'EUR'],
        ],
    ]);

    $response->assertCreated();
    $sheet = CostingSheet::find($response->json('data.id'));
    $line = $sheet->lines->first();

    // Real seeded exchange rates (Chantier 17) — converted amount must not
    // equal the raw EUR figure (i.e. real conversion happened, not a no-op).
    expect((float) $line->line_total)->not->toBe(4.5);
    expect((float) $line->line_total)->toBeGreaterThan(0);
});

test('updating a sheet\'s lines recalculates totals from scratch', function () {
    actingAsUser('purchasing-manager');
    $sheet = CostingSheet::factory()->create(['production_minutes' => 0, 'minute_cost' => 0, 'fixed_cost_coefficient' => 0]);
    $sheet->lines()->create(['section' => 'matiere', 'designation' => 'Old line', 'consumption_qty' => 1, 'unit_price' => 1000, 'currency' => 'MGA']);
    app(\Modules\Inventory\Services\CostingSheetService::class)->recalculate($sheet);
    expect((float) $sheet->fresh()->total_material_cost)->toBe(1000.0);

    $response = $this->putJson("/api/v1/inventory/costing-sheets/{$sheet->id}", [
        'lines' => [
            ['section' => 'matiere', 'designation' => 'New line', 'consumption_qty' => 2, 'unit_price' => 500, 'currency' => 'MGA'],
        ],
    ]);

    $response->assertOk();
    $sheet->refresh();
    expect($sheet->lines()->count())->toBe(1);
    expect($sheet->lines->first()->designation)->toBe('New line');
    expect((float) $sheet->total_material_cost)->toBe(1000.0); // 2 * 500
});

test('duplicating a sheet creates a new draft revision without mutating the original', function () {
    actingAsUser('purchasing-manager');
    $sheet = CostingSheet::factory()->create(['status' => 'approved', 'version' => 1]);
    $sheet->lines()->create(['section' => 'matiere', 'designation' => 'Tissu', 'consumption_qty' => 1, 'unit_price' => 1000, 'currency' => 'MGA']);

    $response = $this->postJson("/api/v1/inventory/costing-sheets/{$sheet->id}/duplicate");

    $response->assertCreated();
    expect($response->json('data.version'))->toBe(2);
    expect($response->json('data.parent_id'))->toBe($sheet->id);
    expect($response->json('data.status'))->toBe('draft');
    expect($sheet->fresh()->status)->toBe('approved'); // original untouched
});

test('a sales-rep (no inventory access) cannot reach costing sheets', function () {
    actingAsUser('sales-rep');

    $this->getJson('/api/v1/inventory/costing-sheets')->assertForbidden();
});

test('an unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/inventory/costing-sheets')->assertUnauthorized();
});

test('a sheet with an invalid section is rejected', function () {
    actingAsUser('purchasing-manager');

    $response = $this->postJson('/api/v1/inventory/costing-sheets', [
        'name' => 'Test invalide',
        'quantity' => 1,
        'base_currency' => 'MGA',
        'lines' => [
            ['section' => 'not_a_real_section', 'designation' => 'x', 'consumption_qty' => 1, 'unit_price' => 1, 'currency' => 'MGA'],
        ],
    ]);

    $response->assertStatus(422);
});

test('deleting a sheet soft-deletes it (excluded from listing/find)', function () {
    // CostingSheet uses SoftDeletes (an already-quoted/approved sheet is an
    // audit trail, not disposable data) — deleting it does not hard-delete
    // its lines at the DB level (the cascadeOnDelete FK only fires on a real
    // row delete), it just removes the sheet from the default query scope.
    actingAsUser('purchasing-manager');
    $sheet = CostingSheet::factory()->create();
    $sheet->lines()->create(['section' => 'matiere', 'designation' => 'x', 'consumption_qty' => 1, 'unit_price' => 1, 'currency' => 'MGA']);

    $this->deleteJson("/api/v1/inventory/costing-sheets/{$sheet->id}")->assertNoContent();

    expect(CostingSheet::find($sheet->id))->toBeNull();
    expect(CostingSheet::withTrashed()->find($sheet->id))->not->toBeNull();

    $listedIds = collect($this->getJson('/api/v1/inventory/costing-sheets')->json('data'))->pluck('id');
    expect($listedIds)->not->toContain($sheet->id);
});
