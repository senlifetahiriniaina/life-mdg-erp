<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Modules\AI\Services\AiContextualAssistantService;

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

/**
 * Build a mock Anthropic API response wrapping the given JSON text.
 */
function mockAnthropicResponse(string $jsonText): array
{
    return [
        'id'      => 'msg_test',
        'type'    => 'message',
        'role'    => 'assistant',
        'content' => [
            ['type' => 'text', 'text' => $jsonText],
        ],
        'model'       => 'claude-sonnet-4-6',
        'stop_reason' => 'end_turn',
        'usage'       => ['input_tokens' => 10, 'output_tokens' => 50],
    ];
}

// ---------------------------------------------------------------------------
// 1. Service disabled when no API key — returns fallback
// ---------------------------------------------------------------------------

test('service returns fallback when ANTHROPIC_API_KEY is empty', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('CRM', 'create_contact');

    expect($guidance['enabled'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// 2. Response always contains all 6 required keys
// ---------------------------------------------------------------------------

test('fallback response contains all required structure keys', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('CRM', 'create_contact');

    expect($guidance)
        ->toHaveKey('enabled')
        ->toHaveKey('what_to_do')
        ->toHaveKey('how_to_do')
        ->toHaveKey('decision_indicators')
        ->toHaveKey('warnings')
        ->toHaveKey('next_actions')
        ->toHaveKey('tips');
});

// ---------------------------------------------------------------------------
// 3. CRM/create_contact fallback has non-empty how_to_do
// ---------------------------------------------------------------------------

test('CRM create_contact fallback has non-empty how_to_do', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('CRM', 'create_contact', 'fr');

    expect($guidance['how_to_do'])->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 4. Accounting/post_invoice fallback has OHADA warning
// ---------------------------------------------------------------------------

test('Accounting post_invoice fallback contains OHADA warning', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Accounting', 'post_invoice', 'fr');

    $allWarnings = implode(' ', $guidance['warnings']);
    expect(strtoupper($allWarnings))->toContain('OHADA');
});

// ---------------------------------------------------------------------------
// 5. English fallback for Accounting/ohada_report warns about OHADA member states
// ---------------------------------------------------------------------------

test('Accounting ohada_report English fallback mentions member states', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Accounting', 'ohada_report', 'en');

    $allText = implode(' ', $guidance['warnings']) . ' ' . $guidance['what_to_do'];
    expect(strtolower($allText))->toContain('ohada');
});

// ---------------------------------------------------------------------------
// 6. Unknown module+action returns the empty guidance structure
// ---------------------------------------------------------------------------

test('unknown module and action returns empty guidance without error', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('UnknownModule', 'unknown_action', 'fr');

    expect($guidance['what_to_do'])->toBe('');
    expect($guidance['how_to_do'])->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 7. Live API call — 200 response, enabled=true, structure intact
// ---------------------------------------------------------------------------

test('live API call returns enabled=true and all keys', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    $fakeJson = json_encode([
        'what_to_do'          => 'Create the contact.',
        'how_to_do'           => ['Step 1', 'Step 2'],
        'decision_indicators' => [['label' => 'Duplicates', 'value' => '0', 'status' => 'ok']],
        'warnings'            => [],
        'next_actions'        => [],
        'tips'                => ['Tip 1'],
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(mockAnthropicResponse($fakeJson), 200),
    ]);

    Cache::flush();

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('CRM', 'create_contact', [], 'fr', 'sales_rep');

    expect($guidance['enabled'])->toBeTrue();
    expect($guidance)->toHaveKeys([
        'enabled', 'what_to_do', 'how_to_do',
        'decision_indicators', 'warnings', 'next_actions', 'tips',
    ]);
});

// ---------------------------------------------------------------------------
// 8. API failure falls back gracefully
// ---------------------------------------------------------------------------

test('API failure returns fallback gracefully without exception', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    Http::fake([
        'api.anthropic.com/*' => Http::response([], 500),
    ]);

    Cache::flush();

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('HR', 'run_payroll', [], 'fr');

    expect($guidance)->toHaveKey('enabled');
    expect($guidance)->toHaveKey('what_to_do');
});

// ---------------------------------------------------------------------------
// 9. Arabic locale — mock returns Arabic guidance
// ---------------------------------------------------------------------------

test('Arabic locale guidance is returned from mocked API', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    $arabicGuidance = json_encode([
        'what_to_do'          => 'أنشئ جهة اتصال جديدة.',
        'how_to_do'           => ['الخطوة 1', 'الخطوة 2'],
        'decision_indicators' => [],
        'warnings'            => [],
        'next_actions'        => [],
        'tips'                => [],
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(mockAnthropicResponse($arabicGuidance), 200),
    ]);

    Cache::flush();

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('CRM', 'create_contact', [], 'ar');

    expect($guidance['what_to_do'])->toContain('جهة');
});

// ---------------------------------------------------------------------------
// 10. Cache key varies by module+action+locale
// ---------------------------------------------------------------------------

test('cache key varies by module, action and locale', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;
        return Http::response(mockAnthropicResponse(json_encode([
            'what_to_do'          => 'Guidance ' . $callCount,
            'how_to_do'           => [],
            'decision_indicators' => [],
            'warnings'            => [],
            'next_actions'        => [],
            'tips'                => [],
        ])), 200);
    });

    Cache::flush();

    $service = new AiContextualAssistantService();
    $service->getGuidance('CRM', 'create_contact', [], 'fr');
    $service->getGuidance('CRM', 'create_contact', [], 'en');   // different locale → different cache key
    $service->getGuidance('HR',  'run_payroll',    [], 'fr');   // different module/action

    expect($callCount)->toBe(3);
});

// ---------------------------------------------------------------------------
// 11. Same cache key is reused (no duplicate API call)
// ---------------------------------------------------------------------------

test('same cache key is reused and API is called only once', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;
        return Http::response(mockAnthropicResponse(json_encode([
            'what_to_do'          => 'Cached.',
            'how_to_do'           => [],
            'decision_indicators' => [],
            'warnings'            => [],
            'next_actions'        => [],
            'tips'                => [],
        ])), 200);
    });

    Cache::flush();

    $service = new AiContextualAssistantService();
    $service->getGuidance('CRM', 'create_contact', [], 'fr', 'user');
    $service->getGuidance('CRM', 'create_contact', [], 'fr', 'user');

    expect($callCount)->toBe(1);
});

// ---------------------------------------------------------------------------
// 12. Response merges fallback defaults safely (no missing keys after merge)
// ---------------------------------------------------------------------------

test('parse response merges fallback defaults so no key is missing', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    // API returns only partial data
    $partial = json_encode([
        'what_to_do' => 'Partial guidance only.',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(mockAnthropicResponse($partial), 200),
    ]);

    Cache::flush();

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('CRM', 'create_contact', [], 'en');

    expect($guidance)
        ->toHaveKey('how_to_do')
        ->toHaveKey('decision_indicators')
        ->toHaveKey('warnings')
        ->toHaveKey('next_actions')
        ->toHaveKey('tips');
});

// ---------------------------------------------------------------------------
// 13. supportedModules() returns all 7 modules with non-empty action lists
// ---------------------------------------------------------------------------

test('supportedModules returns all expected modules with actions', function () {
    $service = new AiContextualAssistantService();
    $modules = $service->supportedModules();

    $expected = ['CRM', 'Accounting', 'HR', 'Inventory', 'Sales', 'POS', 'Setup'];

    foreach ($expected as $module) {
        expect($modules)->toHaveKey($module);
        expect($modules[$module])->not->toBeEmpty();
    }
});

// ---------------------------------------------------------------------------
// 14. POS/close_session fallback has a warning
// ---------------------------------------------------------------------------

test('POS close_session fallback has at least one warning', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('POS', 'close_session', 'fr');

    expect($guidance['warnings'])->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 15. Setup/map_columns fallback has next_actions pointing to execute_import
// ---------------------------------------------------------------------------

test('Setup map_columns fallback suggests execute_import as next action', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Setup', 'map_columns', 'en');

    $actions = array_column($guidance['next_actions'], 'action');
    expect($actions)->toContain('execute_import');
});

// ---------------------------------------------------------------------------
// 16. Inventory/low_stock_alert fallback has critical status indicator
// ---------------------------------------------------------------------------

test('Inventory low_stock_alert fallback has a critical decision indicator', function () {
    Config::set('ai.providers.anthropic.api_key', '');

    $service    = new AiContextualAssistantService();
    $guidance   = $service->fallbackGuidance('Inventory', 'low_stock_alert', 'fr');
    $statuses   = array_column($guidance['decision_indicators'], 'status');

    expect($statuses)->toContain('critical');
});

// ---------------------------------------------------------------------------
// 17. Markdown fences in API response are stripped cleanly
// ---------------------------------------------------------------------------

test('markdown fences in API response are stripped and parsed', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key-12345');

    $withFences = "```json\n" . json_encode([
        'what_to_do'          => 'Guidance with fences.',
        'how_to_do'           => ['Step A'],
        'decision_indicators' => [],
        'warnings'            => [],
        'next_actions'        => [],
        'tips'                => [],
    ]) . "\n```";

    Http::fake([
        'api.anthropic.com/*' => Http::response(mockAnthropicResponse($withFences), 200),
    ]);

    Cache::flush();

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('Sales', 'create_order', [], 'fr');

    expect($guidance['what_to_do'])->toBe('Guidance with fences.');
});
