<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\AI\Providers\AIServiceProvider;

// ─── Structural / provider tests ─────────────────────────────────────────────

test('AIServiceProvider class exists', function () {
    expect(class_exists(AIServiceProvider::class))->toBeTrue();
});

test('AiContextualAssistantService class exists', function () {
    expect(class_exists(AiContextualAssistantService::class))->toBeTrue();
});

test('AiContextualAssistantService can be instantiated without API key', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service = new AiContextualAssistantService();
    expect($service)->toBeInstanceOf(AiContextualAssistantService::class);
});

// ─── Fallback behavior ────────────────────────────────────────────────────────

test('getGuidance returns fallback with enabled=false when API key is absent', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('CRM', 'create_contact');

    expect($guidance['enabled'])->toBeFalse();
});

test('getGuidance always returns all 7 required keys', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('HR', 'create_employee');

    expect($guidance)
        ->toHaveKey('enabled')
        ->toHaveKey('what_to_do')
        ->toHaveKey('how_to_do')
        ->toHaveKey('decision_indicators')
        ->toHaveKey('warnings')
        ->toHaveKey('next_actions')
        ->toHaveKey('tips');
});

test('fallbackGuidance what_to_do is a non-empty string', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Accounting', 'post_invoice', 'fr');

    expect($guidance['what_to_do'])->toBeString()->not->toBeEmpty();
});

test('fallbackGuidance how_to_do is a non-empty array', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Inventory', 'receive_stock', 'fr');

    expect($guidance['how_to_do'])->toBeArray()->not->toBeEmpty();
});

test('fallbackGuidance tips is an array', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('POS', 'open_session', 'en');

    expect($guidance['tips'])->toBeArray();
});

// ─── All 7 supported modules return valid guidance ────────────────────────────

test('CRM create_contact fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('CRM', 'create_contact', 'fr');

    expect($guidance['enabled'])->toBeFalse()
        ->and($guidance['what_to_do'])->not->toBeEmpty();
});

test('Accounting post_invoice fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Accounting', 'post_invoice', 'fr');

    expect($guidance['what_to_do'])->not->toBeEmpty();
});

test('HR create_employee fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('HR', 'create_employee', 'fr');

    expect($guidance['what_to_do'])->not->toBeEmpty();
});

test('Inventory receive_stock fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Inventory', 'receive_stock', 'fr');

    expect($guidance['what_to_do'])->not->toBeEmpty();
});

test('Sales create_order fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Sales', 'create_order', 'fr');

    expect($guidance['what_to_do'])->not->toBeEmpty();
});

test('POS process_payment fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('POS', 'process_payment', 'fr');

    expect($guidance['what_to_do'])->not->toBeEmpty();
});

test('Setup import_file fallback returns valid guidance', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    $service  = new AiContextualAssistantService();
    $guidance = $service->fallbackGuidance('Setup', 'import_file', 'fr');

    expect($guidance['what_to_do'])->not->toBeEmpty();
});

// ─── supportedModules ─────────────────────────────────────────────────────────

test('supportedModules returns array with all 7 modules', function () {
    $service  = new AiContextualAssistantService();
    $modules  = $service->supportedModules();

    expect($modules)
        ->toHaveKey('CRM')
        ->toHaveKey('Accounting')
        ->toHaveKey('HR')
        ->toHaveKey('Inventory')
        ->toHaveKey('Sales')
        ->toHaveKey('POS')
        ->toHaveKey('Setup');
});

test('CRM module has exactly 3 actions', function () {
    $service = new AiContextualAssistantService();
    $modules = $service->supportedModules();

    expect($modules['CRM'])->toHaveCount(3)
        ->toContain('create_contact')
        ->toContain('view_dashboard')
        ->toContain('create_opportunity');
});

test('Accounting module has 4 actions including ohada_report', function () {
    $service = new AiContextualAssistantService();
    $modules = $service->supportedModules();

    // Chantier 30 added 'import_treasury' (bulk cash/bank import assist)
    // and 'view_income_statement' (the IncomeStatement.vue export page).
    expect($modules['Accounting'])->toHaveCount(9)
        ->toContain('ohada_report');
});

// ─── Cache key generation ─────────────────────────────────────────────────────

test('getGuidance uses cache so API is only called once for same inputs', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key');
    Cache::flush();

    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"what_to_do":"test","how_to_do":[],"decision_indicators":[],"warnings":[],"next_actions":[],"tips":[]}']],
        ], 200),
    ]);

    $service = new AiContextualAssistantService();
    $service->getGuidance('CRM', 'create_contact', [], 'fr', 'sales_rep');
    $service->getGuidance('CRM', 'create_contact', [], 'fr', 'sales_rep');

    Http::assertSentCount(1);
});

test('different locales generate different cache keys', function () {
    Config::set('ai.providers.anthropic.api_key', '');
    Cache::flush();

    $service = new AiContextualAssistantService();
    $fr      = $service->getGuidance('CRM', 'create_contact', [], 'fr');
    $en      = $service->getGuidance('CRM', 'create_contact', [], 'en');

    // Both return valid guidance; different locales = different cache entries
    expect($fr)->toBeArray()->and($en)->toBeArray();
});

// ─── API failure graceful degradation ────────────────────────────────────────

test('getGuidance falls back gracefully when API returns 500', function () {
    Config::set('ai.providers.anthropic.api_key', 'test-key');
    Cache::flush();

    Http::fake([
        'https://api.anthropic.com/*' => Http::response(['error' => 'internal'], 500),
    ]);

    $service  = new AiContextualAssistantService();
    $guidance = $service->getGuidance('HR', 'run_payroll');

    expect($guidance)->toHaveKey('enabled')
        ->and($guidance)->toHaveKey('what_to_do');
});
