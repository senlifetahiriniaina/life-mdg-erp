<?php

declare(strict_types=1);

// Replaces Modules/AI/tests/Unit/AIIntegrationTest.php, which duplicated
// Modules/AI/tests/Feature/AIServicesTest.php's coverage of 4 cache-backed stub
// classes (PredictiveAnalyticsService, RecommendationEngineService,
// NaturalLanguageProcessingService, AutomatedInsightsService) that have no HTTP
// layer — Modules/AI/routes/api.php's own docblock documents that the controllers
// for these were never built and the routes are intentionally left commented out
// (backlog / new feature work, not a wiring fix). AIIntegrationTest's own 4
// failures (detectAnomalies() contract mismatch, Cache::getRedis() on the array
// store, undefined array_sort()) were real bugs in that dead, unrouted code —
// not worth fixing since nothing in the product can ever reach it.
//
// Real, routed equivalents for the 4 capabilities already exist and are already
// covered by their own dedicated tests elsewhere:
//   - Predictive models  → Modules/Analytics PredictionController (/api/v1/analytics/predictions)
//   - Recommendations    → Modules/Analytics RecommendationController (/api/v1/analytics/recommendations)
//   - Automated insights → Modules/BI BiInsightsController (/api/v1/bi/insights, see BiInsightsTest.php)
// The one capability with no existing HTTP-level coverage anywhere is natural
// language processing, for which Modules\AI has its own real, routed
// implementation: AiNaturalLanguageSearchService / AiSearchController
// (POST /api/v1/ai/search) — exercised here instead.

it('parses a natural language query and returns structured results', function () {
    actingAsUser('admin');

    $response = $this->postJson('/api/v1/ai/search', [
        'query' => 'clients qui n\'ont pas commandé depuis 3 mois',
        'locale' => 'fr',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'query', 'parsed' => ['module', 'entity', 'filters', 'sort', 'limit'],
            'results', 'count', 'ai_powered', 'suggestion',
        ]);
});

it('infers the correct module from French keywords without a live AI key', function () {
    // .env.testing sets a dummy ANTHROPIC_API_KEY, which would otherwise make
    // ai_powered always true regardless of whether Claude was actually reachable
    // (it just reflects config presence — see AiNaturalLanguageSearchService's
    // $aiEnabled). Clear it so this test exercises the deterministic keyword
    // fallback path, same pattern as AiAssistantTest.php.
    \Illuminate\Support\Facades\Config::set('services.anthropic.key', '');

    actingAsUser('admin');

    $response = $this->postJson('/api/v1/ai/search', [
        'query' => 'factures impayées',
        'locale' => 'fr',
    ]);

    $response->assertOk()
        ->assertJsonPath('parsed.module', 'Accounting')
        ->assertJsonPath('ai_powered', false);
});

it('rejects a query that is too short', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/ai/search', ['query' => 'a'])
        ->assertUnprocessable();
});

it('requires authentication to search', function () {
    $this->postJson('/api/v1/ai/search', ['query' => 'test query'])
        ->assertUnauthorized();
});
