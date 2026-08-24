<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\AI\Models\AiUsageLimit;

/**
 * Chantier 32.2 — 14-layer deep audit of Modules\AI (the module owning
 * AiContextualAssistantService, the multi-provider AIService caller, and
 * AiAssistantController — the one real POST /api/v1/ai/assist endpoint
 * every useAiAssistant() call in the entire app actually hits). Every
 * fix below was confirmed empirically (real HTTP requests / a real
 * `SQLSTATE... no such table` failure reproduced first) before being fixed,
 * matching this session's standing methodology — never fixed from a code
 * read alone.
 */

// -----------------------------------------------------------------------
// 1. `ai_usage_logs` table never existed anywhere in the repo — the
//    module's own real, routed self-service usage endpoint (GET
//    /api/v1/ai/usage/me) 500'd with "SQLSTATE[HY000]: no such table:
//    ai_usage_logs" on every single real call, unconditionally, for every
//    user, confirmed via a real HTTP request before any fix landed. The
//    sibling `ai_usage_limits` table WAS migrated (2026_09_01_000001), which
//    is exactly why this second, equally load-bearing table's absence
//    survived 3 prior audit passes that all touched this exact feature area
//    (Chantier 8.5-light, Chantier 10, Chantier 19 Lot 3) without ever
//    calling myUsage()/adminUsage() against a real request.
// -----------------------------------------------------------------------

test('GET /api/v1/ai/usage/me no longer 500s on the missing ai_usage_logs table', function () {
    actingAsUser('admin');

    $response = $this->getJson('/api/v1/ai/usage/me');

    $response->assertOk()
        ->assertJsonStructure([
            'usage' => ['tokens_in', 'tokens_out', 'cost_usd', 'call_count', 'period_start', 'period_end'],
            'limit',
            'budget' => ['allowed', 'remaining_usd', 'remaining_tokens', 'usage_pct'],
        ]);
    expect($response->json('usage.call_count'))->toBe(0);
});

test('GET /api/v1/ai/admin/usage no longer 500s on the missing ai_usage_logs table', function () {
    actingAsUser('admin');

    $response = $this->getJson('/api/v1/ai/admin/usage');

    $response->assertOk()
        ->assertJsonStructure([
            'total_cost_usd', 'total_calls', 'total_tokens_in', 'total_tokens_out',
            'period_start', 'period_end', 'top_users',
        ]);
    expect($response->json('total_calls'))->toBe(0);
});

test('checkLimit() genuinely reads real usage once a real per-tenant AiUsageLimit exists, without fataling', function () {
    $user = actingAsUser('admin');

    // A real, configured tenant-wide limit — this is the exact path that
    // was previously guaranteed fatal the moment ANY limit existed for a
    // tenant, since checkLimit() -> getLimit() found a real limit ->
    // getUsage() -> "no such table: ai_usage_logs".
    AiUsageLimit::factory()->create([
        'tenant_id'       => $user->company_id ?? 0,
        'user_id'         => null,
        'limit_type'      => 'usd',
        'limit_value'     => 5.0,
        'period'          => 'monthly',
        'block_on_exceed' => true,
        'active'          => true,
    ]);

    Config::set('services.anthropic.key', ''); // static-fallback path, no real API call

    $response = $this->postJson('/api/v1/ai/advise', [
        'module' => 'HR',
        'action' => 'run_payroll',
    ]);

    $response->assertOk()->assertJsonStructure(['advice', 'budget']);
    // advise()'s success-path budget shape (distinct from myUsage()'s) only
    // ever reports `limit_exceeded`, not `allowed` — the real assertion here
    // is that this didn't 429/500, and that the real limit_value ($5) hasn't
    // been exhausted by a single not-yet-logged (enabled:false) call.
    expect($response->json('budget.limit_exceeded'))->toBeFalse();
});

test('a real successful advise() call actually persists a row into ai_usage_logs', function () {
    $user = actingAsUser('admin');

    Config::set('services.anthropic.key', 'fake-key-for-test');

    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => json_encode([
                    'warnings'        => ['Double-check leave balances first.'],
                    'options'         => [],
                    'consequences'    => ['Payslips will be generated.'],
                    'considerations'  => [],
                    'risk_level'      => 'high',
                    'recommendation'  => 'caution',
                ])],
            ],
        ], 200),
    ]);

    expect(DB::table('ai_usage_logs')->count())->toBe(0);

    $response = $this->postJson('/api/v1/ai/advise', [
        'module' => 'HR',
        'action' => 'run_payroll',
    ]);

    $response->assertOk();
    expect($response->json('advice.enabled'))->toBeTrue();

    $rows = DB::table('ai_usage_logs')->get();
    expect($rows)->toHaveCount(1);
    expect((int) $rows->first()->tenant_id)->toBe((int) ($user->company_id ?? 0));
    expect((int) $rows->first()->user_id)->toBe($user->id);
    expect($rows->first()->module)->toBe('HR');
    expect($rows->first()->action)->toBe('run_payroll');
    expect($rows->first()->endpoint_type)->toBe('advise');
    expect((float) $rows->first()->cost_usd)->toBeGreaterThan(0.0);
});

// -----------------------------------------------------------------------
// 2. Systematic cross-check of every real `useAiAssistant(module, action)`
//    call site across the whole app (root resources/js + every
//    Modules/*/resources/js) against supportedModules()/the fallback map —
//    found 3 whole modules and 4 actions on 2 already-registered modules
//    called from real, mounted pages but never registered, the exact
//    "silently resolves to an empty guidance shell" bug class Chantier 30
//    already found and fixed for Strategy. Verified via the real HTTP
//    route, not by instantiating the service directly.
// -----------------------------------------------------------------------

dataset('newly-covered assist pairs', [
    'Analytics.view_dashboard (Modules/Analytics/.../Index.vue + CashflowForecast/Index.vue)' => ['Analytics', 'view_dashboard'],
    'Integration.view_dashboard (Modules/Integration/.../IntegrationsIndex.vue)' => ['Integration', 'view_dashboard'],
    'Security.view_dashboard (Modules/Security/.../Index.vue)' => ['Security', 'view_dashboard'],
    'Helpdesk.view_dashboard (resources/js/Pages/Helpdesk/Tickets/Show.vue)' => ['Helpdesk', 'view_dashboard'],
    'Calendar.calendar_integrations (Modules/Calendar/.../Integrations.vue)' => ['Calendar', 'calendar_integrations'],
    'Calendar.team_calendar (Modules/Calendar/.../Teams.vue)' => ['Calendar', 'team_calendar'],
    'Calendar.view_event (Modules/Calendar/.../Event/Show.vue)' => ['Calendar', 'view_event'],
]);

test('the real POST /api/v1/ai/assist endpoint now returns real guidance for every previously-missing pair', function (string $module, string $action) {
    actingAsUser('admin');
    Config::set('ai.providers.anthropic.api_key', '');

    foreach (['fr', 'en'] as $locale) {
        $response = $this->postJson('/api/v1/ai/assist', [
            'module' => $module,
            'action' => $action,
            'locale' => $locale,
        ]);

        $response->assertOk();
        expect($response->json('what_to_do'))->not->toBe('', "{$module}.{$action} ({$locale}) is still empty");
        expect($response->json('how_to_do'))->not->toBeEmpty("{$module}.{$action} ({$locale}) has no steps");
    }
})->with('newly-covered assist pairs');

test('supportedModules() now reports 38 modules including the 3 newly-registered ones', function () {
    actingAsUser('admin');

    $response = $this->getJson('/api/v1/ai/assist/modules');

    $response->assertOk();
    $modules = $response->json('modules');

    // Chantier 32.7 (14-layer deep audit of Modules\Validation) added a
    // 38th: 'Validation' was never registered at all despite a real,
    // routed ValidationAiAssistController delegating here — the identical
    // "registered controller, zero supportedModules() entry" gap this
    // Chantier 32.2 test file already covers for Analytics/Integration/
    // Security. Bumped from 37, not rolled back — matches this session's
    // established precedent (e.g. Chantier 30's 33->34 Strategy fix) of
    // updating a stale hardcoded total for a real, documented addition
    // rather than treating it as a regression to undo.
    // Chantier 32.28 (14-layer deep audit of Modules\Messaging) added a
    // 39th: 'Messaging' had the identical gap (a real MessagingAiAssistController
    // existed since Chantier 20 but was never registered here).
    // Chantier 38.1 (second deep 14-layer audit of Modules\Core) added a
    // 40th: 'Core' — the one conspicuously absent module from this
    // otherwise near-complete registry, despite the real, routed
    // AI-assisted import pipeline behind Import/Index.vue never calling
    // useAiAssistant() at all.
    expect($modules)->toHaveCount(40);
    expect(array_keys($modules))->toContain('Analytics', 'Integration', 'Security');
    expect($modules['Helpdesk'])->toContain('view_dashboard');
    expect($modules['Calendar'])->toContain('calendar_integrations', 'team_calendar', 'view_event');
});

// -----------------------------------------------------------------------
// 3. `module:AI` RBAC gate — this module's own route groups never gated on
//    the tenant_modules toggle every sibling business module gates on, so
//    any authenticated user could reach /api/v1/ai/* even for a tenant that
//    had explicitly disabled the AI module. Confirmed both directions: a
//    tenant with AI disabled is denied, and the existing (unrelated) test
//    suite - which all go through actingAsUser()'s AI-enabled fixture -
//    still pass unmodified (see the full-suite regression run).
// -----------------------------------------------------------------------

test('a tenant with the AI module explicitly disabled is denied by the new module:AI gate', function () {
    $user = actingAsUser('admin');

    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'AI', 'department' => null],
        ['enabled' => false, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    $response = $this->postJson('/api/v1/ai/assist', [
        'module' => 'CRM',
        'action' => 'create_contact',
    ]);

    $response->assertForbidden();
});

test('a tenant with the AI module enabled (the normal case) still reaches the endpoint', function () {
    actingAsUser('admin'); // actingAsUser() enables 'AI' among the tenant's modules

    $response = $this->postJson('/api/v1/ai/assist', [
        'module' => 'CRM',
        'action' => 'create_contact',
    ]);

    $response->assertOk();
});

// -----------------------------------------------------------------------
// 4. Layer 9 (fake/dead) — PredictiveAnalyticsService, RecommendationEngine
//    Service, NaturalLanguageProcessingService, AutomatedInsightsService,
//    and AnthropicCacheService were confirmed to have zero real callers
//    anywhere in the repo (each was either fake demo scaffolding with
//    uniqid()/rand() and a Cache::getRedis() call that fatals outright on
//    this app's real `file` cache driver, or a functional duplicate of a
//    real, live implementation elsewhere — see AIServiceProvider's
//    docblock) and deleted. AiAnomaly/AiRecommendation — real, migrated
//    tables with a real backing model but zero readers/writers anywhere —
//    were dropped the same way. Locked in so a future re-add doesn't slip
//    back in unnoticed.
// -----------------------------------------------------------------------

test('the 5 confirmed-dead AI services no longer exist', function () {
    expect(class_exists(\Modules\AI\Services\PredictiveAnalyticsService::class))->toBeFalse();
    expect(class_exists(\Modules\AI\Services\RecommendationEngineService::class))->toBeFalse();
    expect(class_exists(\Modules\AI\Services\NaturalLanguageProcessingService::class))->toBeFalse();
    expect(class_exists(\Modules\AI\Services\AutomatedInsightsService::class))->toBeFalse();
    expect(class_exists(\Modules\AI\Services\AnthropicCacheService::class))->toBeFalse();
});

test('the 2 confirmed-dead AI tables (ai_anomalies, ai_recommendations) no longer exist', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('ai_anomalies'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasTable('ai_recommendations'))->toBeFalse();
});

test('the real, live anomaly-detection feature (a genuinely different code path) still works after the dead-table cleanup', function () {
    actingAsUser('admin');

    $response = $this->getJson('/api/v1/ai/anomalies');

    $response->assertOk()->assertJsonStructure(['data', 'count']);
});

// -----------------------------------------------------------------------
// 5. AiUsageLimitFactory was scaffold boilerplate (fake()->word() on every
//    FK/decimal/boolean column, plus a dozen phantom columns that don't
//    exist on ai_usage_limits at all) — confirmed empirically to fatal
//    every real AiUsageLimitFactory::create() call with "no column named
//    name" before being fixed. Every test above that creates a real
//    AiUsageLimit already exercises the fix; this test locks it in
//    explicitly and on its own.
// -----------------------------------------------------------------------

test('AiUsageLimit::factory() creates a real, valid row matching the real schema', function () {
    $limit = AiUsageLimit::factory()->create();

    expect($limit->tenant_id)->toBeInt();
    expect($limit->limit_type)->toBeIn(['usd', 'tokens']);
    expect($limit->period)->toBeIn(['daily', 'weekly', 'monthly']);
    expect($limit->active)->toBeTrue();
});
