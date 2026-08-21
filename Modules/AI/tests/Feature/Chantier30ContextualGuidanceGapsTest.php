<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * Chantier 30 — closes 2 real gaps found while confirming the AI-assist
 * layer (route/controller/view/model/data-format/security/RBAC verification
 * of "assistance IA selon le contexte de l'écran en cours" and "assistance
 * sur l'import/export en masse", per this session's standing empirical
 * discipline):
 *
 * 1. 'Strategy' was never registered in supportedModules()/the fallback map
 *    despite 2 real Vue pages already calling useAiAssistant('Strategy',
 *    ...) — every real call silently degraded to an empty emptyGuidance()
 *    shell (enabled:false, every field blank) instead of real guidance, and
 *    7 more Strategy pages had no AI-assist call at all. All 9 Strategy
 *    actions now have real fr+en fallback text, and all 9 real Strategy
 *    pages call useAiAssistant.
 * 2. The two real bulk-data-import flows outside Setup — Inventory's
 *    Stock/Import.vue (Chantier 16) and Accounting's TreasuryImport/
 *    Index.vue (Chantier 15) — had no contextual guidance at all, unlike
 *    Setup's own import wizard. Both now have real assist actions.
 */
test('every Strategy action returns real, non-empty fallback guidance in French and English', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service = new AiContextualAssistantService();

    $actions = [
        'view_dashboard', 'view_cascade_map', 'view_ratios', 'view_plans',
        'view_plan_detail', 'view_benchmarks', 'view_correlations',
        'view_objectives', 'view_sector_kpi',
    ];

    foreach ($actions as $action) {
        $fr = $service->getGuidance('Strategy', $action, [], 'fr');
        $en = $service->getGuidance('Strategy', $action, [], 'en');

        expect($fr['what_to_do'])->not->toBe('', "Strategy.{$action} (fr) is empty");
        expect($fr['how_to_do'])->not->toBeEmpty("Strategy.{$action} (fr) has no steps");
        expect($en['what_to_do'])->not->toBe('', "Strategy.{$action} (en) is empty");
        expect($fr['enabled'])->toBeFalse();
    }
});

test('Strategy is registered in supportedModules with all 9 real screen actions', function () {
    $service  = new AiContextualAssistantService();
    $modules  = $service->supportedModules();

    expect($modules)->toHaveKey('Strategy');
    expect($modules['Strategy'])->toEqualCanonicalizing([
        'view_dashboard', 'view_cascade_map', 'view_ratios', 'view_plans',
        'view_plan_detail', 'view_benchmarks', 'view_correlations',
        'view_objectives', 'view_sector_kpi',
    ]);
});

test('bulk import actions (Inventory stock, Accounting treasury) return real fallback guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service = new AiContextualAssistantService();

    $stock = $service->getGuidance('Inventory', 'import_stock', [], 'fr');
    expect($stock['what_to_do'])->toContain('masse');
    expect($stock['enabled'])->toBeFalse();

    $treasury = $service->getGuidance('Accounting', 'import_treasury', [], 'fr');
    expect($treasury['what_to_do'])->toContain('masse');

    $incomeStatement = $service->getGuidance('Accounting', 'view_income_statement', [], 'en');
    expect($incomeStatement['what_to_do'])->not->toBe('');
});

test('the real POST /api/v1/ai/assist endpoint — the one every useAiAssistant() call in the app actually hits — returns real Strategy guidance', function () {
    actingAsUser('admin');

    $response = $this->postJson('/api/v1/ai/assist', [
        'module' => 'Strategy',
        'action' => 'view_sector_kpi',
        'locale' => 'fr',
    ]);

    $response->assertOk();
    $response->assertJsonPath('enabled', false);
    expect($response->json('what_to_do'))->not->toBe('');
});

test('the real POST /api/v1/ai/assist endpoint returns real guidance for the two bulk-import screens', function () {
    actingAsUser('admin');

    $stock = $this->postJson('/api/v1/ai/assist', [
        'module' => 'Inventory',
        'action' => 'import_stock',
        'locale' => 'fr',
    ]);
    $stock->assertOk();
    expect($stock->json('what_to_do'))->not->toBe('');

    $treasury = $this->postJson('/api/v1/ai/assist', [
        'module' => 'Accounting',
        'action' => 'import_treasury',
        'locale' => 'fr',
    ]);
    $treasury->assertOk();
    expect($treasury->json('what_to_do'))->not->toBe('');
});
