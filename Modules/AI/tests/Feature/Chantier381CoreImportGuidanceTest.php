<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * Chantier 38.1 — second deep 14-layer audit pass of Modules\Core (a module
 * already audited once as Chantier 32.1 by a concurrent session on this same
 * branch). Layer 13 (IA) found the same "real page, zero AI wiring" bug class
 * already fixed for Strategy (Chantier 30), Validation (Chantier 32.7), CRM
 * (Chantier 32.15), Sales (Chantier 32.16), and HR (Chantier 32.17): 'Core'
 * was the one conspicuously absent module from AiContextualAssistantService's
 * otherwise near-complete registry, despite the real, routed AI-assisted
 * CSV/XLSX import pipeline (Modules\Core\Http\Controllers\Api\ImportController,
 * confirmed real per Chantier 32.1's own investigation — contrary to a stale
 * CLAUDE.md note claiming it had no real controller) backing the real
 * resources/js/Pages/Import/Index.vue page, which was never calling
 * useAiAssistant() at all. Fixed: 'Core' => ['import_data'] registered with
 * real fr+en fallback text, and the page wired with useAiAssistant('Core',
 * 'import_data') + <AIAssistantPanel>.
 */
test('Core.import_data returns real, non-empty fallback guidance in French and English', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service = new AiContextualAssistantService();

    $fr = $service->getGuidance('Core', 'import_data', [], 'fr');
    $en = $service->getGuidance('Core', 'import_data', [], 'en');

    expect($fr['what_to_do'])->not->toBe('', 'Core.import_data (fr) is empty');
    expect($fr['how_to_do'])->not->toBeEmpty('Core.import_data (fr) has no steps');
    expect($en['what_to_do'])->not->toBe('', 'Core.import_data (en) is empty');
    expect($fr['enabled'])->toBeFalse();
});

test('Core is registered in supportedModules with the import_data action', function () {
    $service = new AiContextualAssistantService();
    $modules = $service->supportedModules();

    expect($modules)->toHaveKey('Core');
    expect($modules['Core'])->toEqualCanonicalizing(['import_data']);
});

test('the real POST /api/v1/ai/assist endpoint returns real Core.import_data guidance', function () {
    actingAsUser('admin');

    $response = $this->postJson('/api/v1/ai/assist', [
        'module' => 'Core',
        'action' => 'import_data',
        'locale' => 'fr',
    ]);

    $response->assertOk();
    $response->assertJsonPath('enabled', false);
    expect($response->json('what_to_do'))->not->toBe('');
});
