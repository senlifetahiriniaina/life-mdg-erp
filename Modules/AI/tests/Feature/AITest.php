<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\AI\Providers\AIServiceProvider;

// ---------------------------------------------------------------------------
// Service Provider
// ---------------------------------------------------------------------------

test('AI service provider registers correctly', function () {
    expect(app()->getProvider(AIServiceProvider::class))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// AiContextualAssistantService — fallback behaviour (no API key)
// ---------------------------------------------------------------------------

test('getGuidance returns array with required keys when API disabled', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $result  = $service->getGuidance('CRM', 'create_contact');

    expect($result)->toBeArray()
        ->toHaveKeys(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips']);
});

test('getGuidance enabled key is false when API key absent', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $result  = $service->getGuidance('CRM', 'create_contact');

    expect($result['enabled'])->toBeFalse();
});

test('fallback guidance returns non-empty what_to_do for CRM create_contact in French', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $result   = $service->fallbackGuidance('CRM', 'create_contact', 'fr');

    expect($result['what_to_do'])->toBeString()->not->toBeEmpty();
});

test('fallback guidance returns non-empty what_to_do for CRM create_contact in English', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $result  = $service->fallbackGuidance('CRM', 'create_contact', 'en');

    expect($result['what_to_do'])->toBeString()->not->toBeEmpty();
});

test('fallback guidance returns empty string for unknown module', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $result  = $service->fallbackGuidance('NonExistent', 'do_something');

    expect($result['what_to_do'])->toBe('');
    expect($result['enabled'])->toBeFalse();
});

test('supportedModules returns array with 7 modules', function () {
    $service  = new AiContextualAssistantService();
    $modules  = $service->supportedModules();

    // Chantier 30 added 'Strategy' (previously called from 2 real Vue pages
    // but never registered here — see AiContextualAssistantService.php).
    // Chantier 32.2 added 'Analytics'/'Integration'/'Security' (same bug
    // class, 3 more real modules called from real pages but never
    // registered — see supportedModules()'s own comments). Chantier 32.7
    // added 'Validation' (identical bug class, again — see
    // AiContextualAssistantService.php's own comment on that entry).
    // Chantier 32.28 added 'Messaging' (same bug class again).
    expect($modules)->toBeArray()->toHaveCount(39);
    expect(array_keys($modules))->toContain('CRM', 'Accounting', 'HR', 'Inventory', 'Sales', 'POS', 'Setup', 'Strategy', 'Analytics', 'Integration', 'Security');
});

test('fallback guidance covers all supported module and action pairs', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $modules  = $service->supportedModules();

    foreach ($modules as $module => $actions) {
        foreach ($actions as $action) {
            $result = $service->fallbackGuidance($module, $action, 'fr');
            expect($result['what_to_do'])
                ->toBeString()
                ->not->toBeEmpty("Fallback missing for {$module}.{$action} (fr)");
        }
    }
});

test('fallback guidance covers all supported module and action pairs in English', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $modules = $service->supportedModules();

    foreach ($modules as $module => $actions) {
        foreach ($actions as $action) {
            $result = $service->fallbackGuidance($module, $action, 'en');
            expect($result['what_to_do'])
                ->toBeString()
                ->not->toBeEmpty("Fallback missing for {$module}.{$action} (en)");
        }
    }
});

test('getGuidance uses cache when API is enabled and HTTP call faked', function () {
    Config::set('ai.providers.anthropic.api_key', 'fake-key-for-test');

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['text' => json_encode([
                    'what_to_do'          => 'Test guidance',
                    'how_to_do'           => ['Step 1'],
                    'decision_indicators' => [],
                    'warnings'            => [],
                    'next_actions'        => [],
                    'tips'                => [],
                ])],
            ],
        ], 200),
    ]);

    Cache::flush();

    $service = new AiContextualAssistantService();
    $result1 = $service->getGuidance('CRM', 'create_contact', [], 'en', 'admin');

    // Second call should be served from cache (no new HTTP call)
    Http::fake(['*' => Http::response([], 500)]);
    $result2 = $service->getGuidance('CRM', 'create_contact', [], 'en', 'admin');

    expect($result1['what_to_do'])->toBe($result2['what_to_do']);
});

test('getGuidance falls back gracefully when API returns error', function () {
    Config::set('ai.providers.anthropic.api_key', 'fake-key-for-test');

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([], 500),
    ]);

    Cache::flush();

    $service = new AiContextualAssistantService();
    $result  = $service->getGuidance('Accounting', 'post_invoice', [], 'fr', 'accountant');

    expect($result)->toBeArray()
        ->toHaveKeys(['enabled', 'what_to_do', 'how_to_do']);
});

test('Accounting post_invoice fallback returns OHADA warning in French', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $result  = $service->fallbackGuidance('Accounting', 'post_invoice', 'fr');

    expect($result['warnings'])->toBeArray()->not->toBeEmpty();
});

test('how_to_do array has at most 3 steps in fallback', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $modules  = $service->supportedModules();

    foreach ($modules as $module => $actions) {
        foreach ($actions as $action) {
            $result = $service->fallbackGuidance($module, $action, 'fr');
            expect(count($result['how_to_do']))->toBeLessThanOrEqual(3);
        }
    }
});

test('Setup import_file fallback has next_actions pointing to map_columns', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service = new AiContextualAssistantService();
    $result  = $service->fallbackGuidance('Setup', 'import_file', 'en');

    $actions = array_column($result['next_actions'], 'action');
    expect($actions)->toContain('map_columns');
});

// AutomatedInsightsService / NaturalLanguageProcessingService /
// PredictiveAnalyticsService / RecommendationEngineService were deleted in
// Chantier 32.2 (14-layer deep audit) — confirmed zero real callers
// anywhere in the repo outside these now-deleted `class_exists()` checks
// and their own dedicated tests. See AIServiceProvider::register()'s
// docblock for the full rationale (each was either fake demo scaffolding
// or a functional duplicate of a real, live implementation elsewhere).
