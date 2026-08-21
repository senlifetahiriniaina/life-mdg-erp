<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\Supplier;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\CostingSheetLine;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\SourcingBenchmark;
use Modules\Strategy\Services\TextileSectorKpiService;

uses(RefreshDatabase::class);

/**
 * Chantier 26 (volet E) — KPI sectoriels textile/EPI, calculés en direct sur
 * les fiches de chiffrage (Chantier 21) et commandes de production
 * (Chantier 23) réelles. RefreshDatabase isole chaque test — les comptes
 * ci-dessous sont donc des égalités exactes, pas des minorants.
 */
function sectorKpiUser(): User
{
    return actingAsUser('finance-manager');
}

function sectorKpiTemplate(): ProductTemplate
{
    return ProductTemplate::factory()->create([
        'code'   => 'PF-TEST-SECTOR-'.uniqid(),
        'name'   => 'Pantalon EPI test',
        'family' => 'produit_fini',
    ]);
}

describe('TextileSectorKpiService — real garment costing/production data', function () {
    test('margin is computed from real total_cost_price vs suggested_selling_price, broken down by product family', function () {
        $template = sectorKpiTemplate();

        CostingSheet::factory()->create([
            'product_template_id'     => $template->id,
            'status'                  => 'approved',
            'total_cost_price'        => 10000,
            'suggested_selling_price' => 15000,
        ]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->marginByFamily();

        expect($result['overall']['sheet_count'])->toBe(1)
            ->and((float) $result['overall']['avg_margin_percent'])->toBe(33.3)
            ->and($result['by_family'])->toHaveCount(1)
            ->and($result['by_family'][0]['family'])->toBe('produit_fini')
            ->and((float) $result['by_family'][0]['avg_margin_percent'])->toBe(33.3);
    });

    test('a draft sheet is excluded from margin calculation regardless of price', function () {
        $template = sectorKpiTemplate();
        CostingSheet::factory()->create([
            'product_template_id'     => $template->id,
            'status'                  => 'draft',
            'total_cost_price'        => 10000,
            'suggested_selling_price' => 15000,
        ]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->marginByFamily();

        expect($result['overall']['sheet_count'])->toBe(0)
            ->and($result['overall']['avg_margin_percent'])->toBeNull()
            ->and($result['by_family'])->toBe([]);
    });

    test('cost structure sums to 100% across its real components', function () {
        CostingSheet::factory()->create([
            'status'                  => 'approved',
            'total_material_cost'     => 8000,
            'total_assembly_cost'     => 500,
            'total_finishing_cost'    => 0,
            'total_value_added_cost'  => 0,
            'washing_cost'            => 0,
            'labor_cost'              => 1000,
            'fixed_cost_coefficient'  => 500,
            'total_cost_price'        => 10000,
        ]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->costStructure();

        expect($result['sheet_count'])->toBe(1);
        $sum = array_sum($result['structure']);
        expect($sum)->toBeGreaterThan(99.0)->toBeLessThan(101.0)
            ->and((float) $result['structure']['Matière'])->toBe(80.0)
            ->and((float) $result['structure']["Main-d'œuvre"])->toBe(10.0);
    });

    test('subcontracting lead time and on-time rate are computed only from delivered orders', function () {
        $supplier = Supplier::factory()->create(['name' => 'Sous-traitant Test']);

        ProductionOrder::factory()->create([
            'subcontractor_supplier_id' => $supplier->id,
            'status'                    => 'delivered',
            'started_at'                => now()->subDays(10),
            'expected_delivery_at'      => now()->subDays(1),
            'delivered_at'              => now()->subDays(2),
        ]);
        // Not delivered — must not affect the average.
        ProductionOrder::factory()->create([
            'subcontractor_supplier_id' => $supplier->id,
            'status'                    => 'in_subcontracting',
            'started_at'                => now()->subDays(3),
            'delivered_at'              => null,
        ]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->subcontractingLeadTime();

        expect($result['overall']['delivered_count'])->toBe(1)
            ->and((float) $result['overall']['avg_lead_time_days'])->toBe(8.0)
            ->and((float) $result['overall']['on_time_percent'])->toBe(100.0)
            ->and($result['by_subcontractor'])->toHaveCount(1)
            ->and($result['by_subcontractor'][0]['subcontractor'])->toBe('Sous-traitant Test');
    });

    test('a late delivery lowers the on-time rate', function () {
        $supplier = Supplier::factory()->create();

        ProductionOrder::factory()->create([
            'subcontractor_supplier_id' => $supplier->id,
            'status'                    => 'delivered',
            'started_at'                => now()->subDays(15),
            'expected_delivery_at'      => now()->subDays(10),
            'delivered_at'              => now()->subDays(2), // 8 days late
        ]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->subcontractingLeadTime();

        expect((float) $result['overall']['on_time_percent'])->toBe(0.0);
    });

    test('a cancelled production order is excluded from the production mix', function () {
        $template = sectorKpiTemplate();
        $sheet    = CostingSheet::factory()->create(['product_template_id' => $template->id]);

        ProductionOrder::factory()->create(['costing_sheet_id' => $sheet->id, 'status' => 'draft', 'quantity' => 50]);
        ProductionOrder::factory()->create(['costing_sheet_id' => $sheet->id, 'status' => 'cancelled', 'quantity' => 999]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->productionMixByFamily();

        expect($result)->toHaveCount(1)
            ->and($result[0]['family'])->toBe('produit_fini')
            ->and($result[0]['order_count'])->toBe(1)
            ->and($result[0]['total_quantity'])->toBe(50);
    });

    test('material price variance only compares a costing line and a benchmark sharing the same product template and currency', function () {
        $template = sectorKpiTemplate();
        $sheet    = CostingSheet::factory()->create(['product_template_id' => $template->id]);

        CostingSheetLine::create([
            'costing_sheet_id'    => $sheet->id,
            'section'             => 'matiere',
            'designation'         => 'Tissu',
            'product_template_id' => $template->id,
            'consumption_qty'     => 1,
            'unit_price'          => 5000,
            'currency'            => 'MGA',
            'line_total'          => 5000,
        ]);

        SourcingBenchmark::create([
            'product_template_id' => $template->id,
            'source'               => 'xm_textiles',
            'unit_price'           => 5500,
            'currency'             => 'MGA',
            'observed_at'          => now()->subDays(3),
        ]);
        // A different currency must not be silently mixed in.
        SourcingBenchmark::create([
            'product_template_id' => $template->id,
            'source'               => 'klopman',
            'unit_price'           => 4,
            'currency'             => 'EUR',
            'observed_at'          => now()->subDays(2),
        ]);

        $svc    = app(TextileSectorKpiService::class);
        $result = $svc->materialPriceVariance();

        expect($result['compared_count'])->toBe(1)
            ->and((float) $result['avg_variance_percent'])->toBe(10.0);
    });

    test('every method degrades to an empty/null result rather than erroring when there is no data at all', function () {
        $svc = app(TextileSectorKpiService::class);

        expect($svc->marginByFamily()['overall']['sheet_count'])->toBe(0)
            ->and($svc->costStructure()['sheet_count'])->toBe(0)
            ->and($svc->subcontractingLeadTime()['overall']['delivered_count'])->toBe(0)
            ->and($svc->productionMixByFamily())->toBe([])
            ->and($svc->materialPriceVariance()['compared_count'])->toBe(0);
    });
});

describe('GET /strategy/sector-kpi', function () {
    test('the web page renders the real Inertia component with all 5 KPI blocks', function () {
        sectorKpiUser();

        $response = $this->get('/strategy/sector-kpi');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Strategy/SectorKpi/Index', false)
                ->has('margin')
                ->has('cost_structure')
                ->has('lead_time')
                ->has('production_mix')
                ->has('material_variance')
            );
    });

    test('an unauthenticated request is redirected, not served', function () {
        $this->get('/strategy/sector-kpi')->assertRedirect();
    });
});
