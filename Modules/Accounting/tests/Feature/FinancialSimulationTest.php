<?php

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FinancialSimulation;
use Modules\Accounting\Models\FinancialSimulationLine;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Sales\Models\SalesOrder;

/**
 * Chantier 18 — upmetrics-style financial simulation: bottom-up projection
 * of manually-entered sale/purchase assumptions into a period-by-period
 * Compte de résultat / Trésorerie / Bilan simplifié, plus a "realize" action
 * that turns a simulated line into a real order + a balanced journal entry.
 */
beforeEach(function () {
    $this->user = actingAsUser('accountant');

    // The chart of accounts is seeded via AccountingDatabaseSeeder (called
    // from DatabaseSeeder since Chantier 12) — reuse the real 411/401 codes.
    if (! ChartOfAccount::where('code', '411')->exists()) {
        ChartOfAccount::factory()->create(['code' => '411', 'name' => 'Clients', 'type' => 'asset']);
    }
    if (! ChartOfAccount::where('code', '401')->exists()) {
        ChartOfAccount::factory()->create(['code' => '401', 'name' => 'Fournisseurs', 'type' => 'liability']);
    }
});

describe('FinancialSimulationService::project() — period-by-period projection', function () {
    test('a monthly recurring sale line compounds by its growth rate and each period balances', function () {
        $sim = FinancialSimulation::factory()->create([
            'granularity' => 'month',
            'start_date' => '2026-08-01',
            'horizon_periods' => 3,
            'opening_cash_balance' => 100000,
        ]);

        FinancialSimulationLine::factory()->create([
            'financial_simulation_id' => $sim->id,
            'type' => 'sale',
            'product_id' => null,
            'quantity' => 10,
            'unit_price' => 5000,
            'recurrence' => 'monthly',
            'start_date' => '2026-08-01',
            'growth_rate_percent' => 10,
        ]);

        $response = $this->getJson("/api/v1/accounting/financial-simulations/{$sim->id}/project");

        $response->assertStatus(200);
        $periods = $response->json('data.periods');

        expect($periods)->toHaveCount(3);
        expect((float) $periods[0]['compte_de_resultat']['chiffre_affaires'])->toBe(50000.0);
        expect((float) $periods[1]['compte_de_resultat']['chiffre_affaires'])->toBe(55000.0);
        expect((float) $periods[2]['compte_de_resultat']['chiffre_affaires'])->toBe(60500.0);

        foreach ($periods as $period) {
            expect($period['bilan_simplifie']['equilibre'])->toBeTrue();
            expect($period['bilan_simplifie']['actif']['total'])->toEqualWithDelta($period['bilan_simplifie']['passif']['total'], 0.01);
        }
    });

    test('a purchase line reduces the period result and trésorerie rolls forward', function () {
        $sim = FinancialSimulation::factory()->create([
            'granularity' => 'week',
            'start_date' => '2026-08-01',
            'horizon_periods' => 2,
            'opening_cash_balance' => 20000,
        ]);

        FinancialSimulationLine::factory()->create([
            'financial_simulation_id' => $sim->id,
            'type' => 'purchase',
            'quantity' => 5,
            'unit_price' => 1000,
            'recurrence' => 'once',
            'start_date' => '2026-08-01',
        ]);

        $response = $this->getJson("/api/v1/accounting/financial-simulations/{$sim->id}/project");
        $periods = $response->json('data.periods');

        expect((float) $periods[0]['compte_de_resultat']['charges'])->toBe(5000.0);
        expect((float) $periods[0]['tresorerie']['fermeture'])->toBe(15000.0);
        // Second period has no more occurrences (recurrence=once) — cash stays flat.
        expect((float) $periods[1]['tresorerie']['fermeture'])->toBe(15000.0);
    });
});

describe('FinancialSimulationService::realizeLine() — converting a simulated value into a real one', function () {
    test('realizing a sale line creates a confirmed SalesOrder and a balanced journal entry', function () {
        $category = Category::factory()->create(['default_sale_account_code' => '411']);
        $product = Product::factory()->create(['category_id' => $category->id, 'selling_price' => 8000]);

        $sim = FinancialSimulation::factory()->create();
        $line = FinancialSimulationLine::factory()->create([
            'financial_simulation_id' => $sim->id,
            'type' => 'sale',
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => null,
            'status' => 'simulated',
        ]);

        $response = $this->postJson("/api/v1/accounting/financial-simulation-lines/{$line->id}/realize");

        $response->assertStatus(200);
        $line->refresh();

        expect($line->status)->toBe('realized');
        expect($line->realized_type)->toBe(SalesOrder::class);

        $order = SalesOrder::find($line->realized_id);
        expect($order)->not->toBeNull();
        expect($order->status)->toBe('confirmed');
        expect((float) $order->total)->toBe(16000.0);

        $entryId = $response->json('data.journal_entry_id');
        $entry = \Modules\Accounting\Models\JournalEntry::find($entryId);
        expect((float) $entry->lines->sum('debit'))->toBe((float) $entry->lines->sum('credit'));
    });

    test('realizing a purchase line creates a real PurchaseOrder with a line item', function () {
        $category = Category::factory()->create(['default_purchase_account_code' => '401']);
        $product = Product::factory()->create(['category_id' => $category->id, 'cost_price' => 3000]);
        $supplier = Supplier::factory()->create();

        $sim = FinancialSimulation::factory()->create();
        $line = FinancialSimulationLine::factory()->create([
            'financial_simulation_id' => $sim->id,
            'type' => 'purchase',
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 4,
            'unit_price' => null,
            'status' => 'simulated',
        ]);

        $response = $this->postJson("/api/v1/accounting/financial-simulation-lines/{$line->id}/realize");

        $response->assertStatus(200);
        $line->refresh();
        expect($line->realized_type)->toBe(PurchaseOrder::class);

        $po = PurchaseOrder::find($line->realized_id);
        expect($po->lines()->count())->toBe(1);
        expect((float) $po->lines()->first()->line_total)->toBe(12000.0);
    });

    test('a realized line cannot be realized twice', function () {
        $category = Category::factory()->create(['default_sale_account_code' => '411']);
        $product = Product::factory()->create(['category_id' => $category->id, 'selling_price' => 1000]);
        $sim = FinancialSimulation::factory()->create();
        $line = FinancialSimulationLine::factory()->create([
            'financial_simulation_id' => $sim->id,
            'type' => 'sale',
            'product_id' => $product->id,
            'unit_price' => null,
            'status' => 'realized',
        ]);

        $response = $this->postJson("/api/v1/accounting/financial-simulation-lines/{$line->id}/realize");

        $response->assertStatus(422);
    });

    test('a realized line is excluded from the next projection (no double-count)', function () {
        $category = Category::factory()->create(['default_sale_account_code' => '411']);
        $product = Product::factory()->create(['category_id' => $category->id, 'selling_price' => 5000]);

        $sim = FinancialSimulation::factory()->create([
            'granularity' => 'month',
            'start_date' => now()->startOfMonth()->toDateString(),
            'horizon_periods' => 1,
        ]);
        $line = FinancialSimulationLine::factory()->create([
            'financial_simulation_id' => $sim->id,
            'type' => 'sale',
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => null,
            'recurrence' => 'once',
            'start_date' => now()->startOfMonth()->toDateString(),
            'status' => 'simulated',
        ]);

        $this->postJson("/api/v1/accounting/financial-simulation-lines/{$line->id}/realize")->assertStatus(200);

        $projection = $this->getJson("/api/v1/accounting/financial-simulations/{$sim->id}/project")->json('data');

        expect((float) $projection['periods'][0]['compte_de_resultat']['chiffre_affaires'])->toBe(0.0);
    });
});

describe('RBAC', function () {
    test('a role without accounting access is denied', function () {
        actingAsUser('sales-rep');

        $sim = FinancialSimulation::factory()->create();

        $this->getJson('/api/v1/accounting/financial-simulations')->assertStatus(403);
        $this->postJson('/api/v1/accounting/financial-simulations', ['name' => 'x'])->assertStatus(403);
    });
});
