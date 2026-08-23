<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\Supplier;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\CostingSheetLine;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Strategy\Models\Correlation;
use Modules\Strategy\Models\StrategyKeyResult;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;

uses(RefreshDatabase::class);

/**
 * Chantier 29 — "Rapport de pilotage stratégique" (PDF + Excel), le premier
 * export de tout le module Strategy. Verrouille les vraies routes HTTP, pas
 * seulement le service — et un vrai bug trouvé par exécution empirique via
 * tinker, pas par relecture de code : `Eloquent::relationsToArray()` snake-
 * case le nom d'une relation eager-loaded même si `with(['keyResults'])`
 * l'appelle en camelCase, donc OkrService::getOkrTree()'s objectives array
 * exposait `key_results`, jamais `keyResults` comme le reste du frontend de
 * ce module semblait supposer — la première version de ce chantier's Blade/
 * Sheet lisait la mauvaise clé et aurait silencieusement omis chaque
 * résultat clé de tout rapport généré.
 */
function chantier29Report(): array
{
    $user = actingAsUser('finance-manager');
    $company = Company::create(['name' => 'Chantier29 Co '.uniqid(), 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $user->forceFill(['company_id' => $company->id])->save();

    $plan = StrategyPlan::create([
        'tenant_id'    => (string) $company->id,
        'name'         => 'Plan Chantier29',
        'status'       => 'active',
        'period_start' => 2026,
        'period_end'   => 2027,
    ]);
    $objective = StrategyObjective::create([
        'plan_id'  => $plan->id,
        'level'    => 'annual',
        'title'    => 'Objectif Chantier29',
        'status'   => 'active',
        'weight'   => 1,
        'progress' => 40,
    ]);
    StrategyKeyResult::create([
        'objective_id'  => $objective->id,
        'title'         => 'Résultat clé Chantier29',
        'target_value'  => 10000000,
        'current_value' => 4000000,
        'unit'          => 'MGA',
        'progress'      => 40,
    ]);
    Correlation::create([
        'kpi_a'        => 'CRM:win_rate',
        'kpi_b'        => 'Sales:revenue_growth_rate',
        'coefficient'  => 0.82,
        'lag_periods'  => 0,
        'confidence'   => 92,
    ]);

    $template = ProductTemplate::factory()->create([
        'code'   => 'PF-C29-'.uniqid(),
        'name'   => 'Pantalon EPI Chantier29',
        'family' => 'produit_fini',
    ]);
    $sheet = CostingSheet::factory()->create([
        'product_template_id'     => $template->id,
        'status'                  => 'approved',
        'total_cost_price'        => 10000,
        'suggested_selling_price' => 15000,
        // Chantier 32: CostingSheet/ProductionOrder now carry a real
        // company_id, and the executive-report export threads the real
        // caller's own company_id through TextileSectorKpiService — must
        // match $company->id above or this fixture's data is correctly
        // excluded from the report as another company's data.
        'company_id'              => $company->id,
    ]);
    CostingSheetLine::create([
        'costing_sheet_id'    => $sheet->id,
        'section'             => 'matiere',
        'designation'         => 'Tissu Chantier29',
        'product_template_id' => $template->id,
        'consumption_qty'     => 1,
        'unit_price'          => 5000,
        'currency'            => 'MGA',
        'line_total'          => 5000,
    ]);
    $supplier = Supplier::factory()->create(['name' => 'Sous-traitant Chantier29']);
    ProductionOrder::factory()->create([
        'costing_sheet_id'           => $sheet->id,
        'subcontractor_supplier_id'  => $supplier->id,
        'status'                     => 'delivered',
        'started_at'                 => now()->subDays(10),
        'expected_delivery_at'       => now()->subDays(1),
        'delivered_at'               => now()->subDays(2),
        'company_id'                 => $company->id,
    ]);

    return compact('user', 'company', 'plan', 'objective');
}

describe('GET /api/v1/strategy/executive-report/export/{pdf,excel}', function () {
    test('PDF export streams a real, valid PDF containing the real seeded data', function () {
        chantier29Report();

        $response = $this->get('/api/v1/strategy/executive-report/export/pdf');

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf');
        $content = $response->getContent();
        expect(substr($content, 0, 4))->toBe('%PDF');
        // Real content check, not just a PDF-shaped blob: DomPDF's page
        // content streams are Flate-compressed, so the visible text only
        // appears after inflating each `stream ... endstream` block, then
        // decoding its `[...] TJ` runs as UTF-16BE (confirmed empirically
        // against a real generated PDF via tinker + a throwaway script
        // before writing this assertion, not guessed at).
        preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $streams);
        $text = '';
        foreach ($streams[1] as $stream) {
            $inflated = @gzuncompress($stream);
            if ($inflated === false || ! str_contains($inflated, 'TJ')) {
                continue;
            }
            preg_match_all('/\[(.*?)\]\s*TJ/s', $inflated, $runs);
            foreach ($runs[1] as $run) {
                preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $run, $parts);
                foreach ($parts[0] as $part) {
                    $raw = substr($part, 1, -1);
                    $text .= @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
                }
            }
        }
        // str_contains()->toBeTrue() rather than expect($text)->toContain()
        // — the latter's failure message dumps the whole multi-KB text blob.
        expect(str_contains($text, 'Plan Chantier29'))->toBeTrue()
            ->and(str_contains($text, 'Objectif Chantier29'))->toBeTrue()
            ->and(str_contains($text, 'Résultat clé Chantier29'))->toBeTrue()
            ->and(str_contains($text, 'CRM:win_rate'))->toBeTrue();
    });

    test('Excel export streams a real, valid multi-sheet xlsx containing the real seeded data', function () {
        chantier29Report();

        $response = $this->get('/api/v1/strategy/executive-report/export/excel');

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('spreadsheetml');
        // Maatwebsite\Excel\Facades\Excel::download() returns a
        // BinaryFileResponse (a real temp file on disk), not a buffered
        // Response — ->getContent() returns false for it (confirmed
        // empirically), the real bytes live at ->getFile()->getPathname().
        $filePath = $response->baseResponse->getFile()->getPathname();
        $content  = file_get_contents($filePath);
        expect(substr($content, 0, 2))->toBe('PK');

        $tmpFile = tempnam(sys_get_temp_dir(), 'c29xlsx').'.xlsx';
        file_put_contents($tmpFile, $content);

        $zip = new ZipArchive();
        $zip->open($tmpFile);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        preg_match_all('/name="([^"]+)"/', $workbookXml, $sheetNames);
        expect($sheetNames[1])->toBe(['Ratios', 'Plans', 'Corrélations', 'OKR', 'KPI Sectoriels']);

        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($tmpFile);

        expect(str_contains($shared, 'Plan Chantier29'))->toBeTrue()
            ->and(str_contains($shared, 'Objectif Chantier29'))->toBeTrue()
            ->and(str_contains($shared, 'Résultat clé Chantier29'))->toBeTrue()
            ->and(str_contains($shared, 'Sous-traitant Chantier29'))->toBeTrue();
    });

    test('a role outside the Strategy gate is denied on both export routes', function () {
        actingAsUser('sales-rep');

        $this->get('/api/v1/strategy/executive-report/export/pdf')->assertForbidden();
        $this->get('/api/v1/strategy/executive-report/export/excel')->assertForbidden();
    });

    test('an unauthenticated JSON request is denied with 401, not silently served', function () {
        $this->getJson('/api/v1/strategy/executive-report/export/pdf')->assertUnauthorized();
    });

    test('the report degrades gracefully to empty sections when a tenant has no strategy data at all', function () {
        actingAsUser('finance-manager');

        $response = $this->get('/api/v1/strategy/executive-report/export/pdf');

        $response->assertOk();
        expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
    });
});
