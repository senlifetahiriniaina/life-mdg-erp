<?php

declare(strict_types=1);

/**
 * Chantier 32.8 — 14-layer deep audit of Modules\Shared. Locks in every real
 * bug found and fixed, empirically (real HTTP requests / real schema
 * introspection), not just re-reading code:
 *
 *  1. Layer 1/3 (route/vue): resources/js/Pages/Index.vue was a real page
 *     (real useAiAssistant('Shared','view_dashboard') call, real registered
 *     AI-assist action) with ZERO route anywhere in the app — the module had
 *     no routes/web.php at all and RouteServiceProvider::map() only ever
 *     called mapApiRoutes(). Fixed with a new routes/web.php + mapWebRoutes().
 *  2. Layer 5/9/10 (data format / fake-dead / relational): `shared_preferences`
 *     — a real migrated table — had zero Eloquent model and zero reader/
 *     writer anywhere in the app, confirmed via repo-wide grep. Dropped.
 *  3. Layer 9 (fake/dead): Modules\Shared\Services\{SentimentAnalysisService,
 *     UnifiedForecastingService} + their exceptions {SentimentException,
 *     ForecastingException} — both confirmed fully dead (zero callers
 *     anywhere outside their own module's tests) and each a functional
 *     duplicate of a real, live implementation elsewhere (Helpdesk's own
 *     ticket-aware SentimentAnalysisService; Analytics' ForecastingEngineService).
 *     Deleted. Modules\Shared\Exceptions\ValidationException — confirmed
 *     zero real throw sites anywhere (every other "ValidationException" hit
 *     in the app resolves to Laravel's own Illuminate\Validation\
 *     ValidationException, a same-basename collision) — deleted too.
 *  4. Layer 14f (perf): CountryController/CurrencyController re-queried
 *     genuinely static reference data on every single call with zero
 *     caching. Added a 1h Cache::remember() (matching PersonalizationFramework's
 *     own established TTL convention in this module), verified the second
 *     call for the same params issues zero additional DB queries.
 *
 * Re-verifies still correct (not re-discovering, per the task's own
 * instruction): auth:sanctum+module:Shared+role: gating on the API group
 * (Chantier 8.5-light/10), CurrencyController::convert()'s real math against
 * the Chantier 17 seeded illustrative rates, PersonalizationFramework/
 * BaseService/MultiTenantScope/BaseAsyncJob remain real+correct+adopted
 * (or safely orphaned-but-exported) shared infrastructure.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function chantier32SharedUser(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ---------------------------------------------------------------------------
// Layer 1/3 — the real web page is now actually reachable
// ---------------------------------------------------------------------------

test('the real Shared/Index page is reachable at /shared and renders the real Inertia component', function () {
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company);

    test()->actingAs($user)
        ->get('/shared')
        ->assertOk()
        // The 2nd `false` param disables inertia-laravel's built-in
        // "does this .vue file exist" check, which only ever looks under
        // the root resources/js/Pages/ — not Modules/*/resources/js/Pages/,
        // where this real page actually lives (same precedent already used
        // by Modules/Payroll/tests/Feature/Chantier83PayrollWebRouteTest.php).
        ->assertInertia(fn ($page) => $page->component('Shared/Index', false));
});

test('/shared is denied for a role outside the module gate, same as the API group', function () {
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company, 'sales-rep');

    test()->actingAs($user)->get('/shared')->assertStatus(403);
});

test('/shared requires authentication', function () {
    test()->get('/shared')->assertRedirect();
});

// ---------------------------------------------------------------------------
// Layer 5/9/10 — shared_preferences dropped, dead classes gone for good
// ---------------------------------------------------------------------------

test('shared_preferences table no longer exists — confirmed dead, dropped', function () {
    expect(Schema::hasTable('shared_preferences'))->toBeFalse();
});

test('the deleted dead-duplicate services and their orphaned exceptions no longer exist', function () {
    expect(class_exists(\Modules\Shared\Services\BaseService::class))->toBeTrue(); // still real
    expect(class_exists('Modules\Shared\Services\SentimentAnalysisService'))->toBeFalse();
    expect(class_exists('Modules\Shared\Services\UnifiedForecastingService'))->toBeFalse();
    expect(class_exists('Modules\Shared\Exceptions\SentimentException'))->toBeFalse();
    expect(class_exists('Modules\Shared\Exceptions\ForecastingException'))->toBeFalse();
    expect(class_exists('Modules\Shared\Exceptions\ValidationException'))->toBeFalse();

    // The real, still-live duplicate this session confirmed as the actual
    // implementation used app-wide (AiResponseService/AgentPerformanceAnalytics
    // Service/PredictiveEscalationService/SatisfactionPredictionService all
    // resolve app(SentimentAnalysisService::class) against THIS class, not
    // the deleted Shared one).
    expect(class_exists(\Modules\Helpdesk\Services\SentimentAnalysisService::class))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Re-confirm still correct: RBAC gate + real currency math (Chantier 17/
// 8.5-light/10), now against the cached code path
// ---------------------------------------------------------------------------

test('the API group still requires auth:sanctum + module:Shared + role gating', function () {
    test()->getJson('/api/v1/shared/countries')->assertStatus(401);

    $company = Company::factory()->create();
    $user = chantier32SharedUser($company, 'sales-rep');
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries')->assertStatus(403);
});

test('convert() still computes the real EUR->MGA math against the seeded illustrative rates, with caching now enabled', function () {
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/currencies/convert', [
        'amount' => 4.5, 'from' => 'EUR', 'to' => 'MGA',
    ])->assertOk();

    expect((float) $response->json('data.converted_amount'))->toEqual(22011.0);
});

test('convert() 404s cleanly for an unknown currency code rather than a raw ModelNotFoundException', function () {
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company);

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/currencies/convert', [
        'amount' => 10, 'from' => 'ZZZ', 'to' => 'USD',
    ])->assertStatus(404);
});

// ---------------------------------------------------------------------------
// Layer 14f — caching genuinely eliminates the repeat query, does not stale
// across different filter params
// ---------------------------------------------------------------------------

test('CountryController::index() issues zero additional shared_countries queries on a cached repeat call', function () {
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company);

    // Prime the cache.
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries')->assertOk();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'shared_countries')) {
            $queries[] = $query->sql;
        }
    });

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries')->assertOk();

    expect($queries)->toBeEmpty();
});

test('CurrencyController caching is keyed by filter params — active-only and include_inactive never collide', function () {
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company);

    \Modules\Shared\Models\Currency::create([
        'code' => 'ZZI', 'name' => 'Inactive Test', 'symbol' => 'Z', 'decimals' => 2, 'is_active' => false,
    ]);

    $activeOnly = test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/currencies')->assertOk();
    expect(collect($activeOnly->json('data'))->pluck('code'))->not->toContain('ZZI');

    $withInactive = test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/currencies?include_inactive=1')->assertOk();
    expect(collect($withInactive->json('data'))->pluck('code'))->toContain('ZZI');
});

// ---------------------------------------------------------------------------
// Layer 13 — AI: 'Shared' really is registered with real fr+en fallback text
// for its one real caller's action, exercised via the real HTTP endpoint
// (no mock — this sandbox's ANTHROPIC_API_KEY is empty, so this genuinely
// exercises the fallback path a real deployment without a configured
// provider would hit too).
// ---------------------------------------------------------------------------

test('POST /api/v1/shared/ai/assist returns real non-empty fallback guidance for view_dashboard, fr and en', function () {
    $company = Company::factory()->create();
    $user = chantier32SharedUser($company);

    $fr = test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/ai/assist', [
        'action' => 'view_dashboard', 'locale' => 'fr',
    ])->assertOk();
    expect($fr->json('enabled'))->toBeFalse(); // no provider configured -> static fallback
    expect($fr->json('what_to_do'))->not->toBeEmpty();

    $en = test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/ai/assist', [
        'action' => 'view_dashboard', 'locale' => 'en',
    ])->assertOk();
    expect($en->json('what_to_do'))->not->toBeEmpty();
    expect($en->json('what_to_do'))->not->toBe($fr->json('what_to_do'));
});

test("AiContextualAssistantService::supportedModules() lists 'Shared' with its one real action", function () {
    $modules = app(\Modules\AI\Services\AiContextualAssistantService::class)->supportedModules();

    expect($modules)->toHaveKey('Shared')
        ->and($modules['Shared'])->toContain('view_dashboard');
});
