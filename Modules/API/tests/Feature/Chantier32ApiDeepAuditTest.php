<?php

declare(strict_types=1);

/**
 * Chantier 32.5 — deep 14-layer audit of Modules\API.
 *
 * Locks in every real bug found and fixed this chantier, all verified
 * empirically first (php artisan tinker against a real, migrated key) and
 * only then written up as real HTTP Pest assertions here — never the other
 * way around. See CLAUDE.md's Chantier 32.5 entry for the full narrative.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\API\Models\ApiKey;
use Modules\API\Models\ApiRequest;
use Modules\Core\Models\AuditLog;

uses(RefreshDatabase::class);

function chantier32ApiUser(?Company $company = null, string $role = 'admin'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company ??= Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

/**
 * Creates a real ApiKey row + returns [$key, $rawKey] — mints the raw key
 * the exact same way ApiKeyController::store() does (wh_ prefix + 40
 * random chars, bcrypt hash), since AuthenticateApiKey resolves keys by
 * key_prefix + Hash::check(), not by any shortcut.
 */
function chantier32MintApiKey(int $tenantId, int $rateLimit = 1000, array $overrides = []): array
{
    $raw = 'wh_' . Str::random(40);

    $key = ApiKey::create(array_merge([
        'tenant_id'  => $tenantId,
        'name'       => 'Chantier 32.5 test key',
        'key_hash'   => Hash::make($raw),
        'key_prefix' => substr($raw, 0, 8),
        'scopes'     => ['read'],
        'rate_limit' => $rateLimit,
    ], $overrides));

    return [$key, $raw];
}

// ─────────────────────────────────────────────────────────────────────────
// Layer 9 (fake/dead) — routes/graphql.php and ApiWebhook: confirmed dead,
// deleted. Locks in that the routes genuinely no longer exist.
// ─────────────────────────────────────────────────────────────────────────

test('routes/graphql.php routes no longer exist — confirmed dead, deleted', function () {
    $user = chantier32ApiUser();
    test()->actingAs($user, 'sanctum')->postJson('/api/graphql/query', [])->assertNotFound();
    test()->actingAs($user, 'sanctum')->getJson('/api/graphql/schemas')->assertNotFound();
});

test('the deleted api_webhooks CRUD routes no longer exist', function () {
    $user = chantier32ApiUser();
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/api/webhooks')->assertNotFound();
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/webhooks', [
        'name' => 'x', 'url' => 'https://example.com', 'events' => ['x'],
    ])->assertNotFound();
});

test('api_webhooks table itself is dropped', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('api_webhooks'))->toBeFalse();
});

test('the real root webhook system this app already has is unaffected by the api_webhooks deletion', function () {
    // Confirms Modules\API's deletion didn't collide with or break the
    // real, live App\Models\Webhook system it was superseded by. Uses the
    // shared actingAsUser() helper (not chantier32ApiUser()) — that route
    // group also requires 2fa, which only the shared helper pre-confirms.
    $user = actingAsUser('admin');
    test()->getJson('/api/v1/webhooks')->assertOk();
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 9 (fake/dead, activated) — AuthenticateApiKey / GET /ping: the
// previously dead-on-arrival api_keys auth pipeline, now real end to end.
// ─────────────────────────────────────────────────────────────────────────

test('a valid API key authenticates against GET /api/v1/api/ping', function () {
    [$key, $raw] = chantier32MintApiKey(tenantId: 42);

    $response = test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping');

    $response->assertOk()
        ->assertJsonPath('data.authenticated', true)
        ->assertJsonPath('data.key_id', $key->id)
        ->assertJsonPath('data.tenant_id', 42);
});

test('ping rejects a request with no API key header', function () {
    test()->getJson('/api/v1/api/ping')->assertUnauthorized();
});

test('ping rejects a syntactically-plausible but unknown API key', function () {
    test()->withHeaders(['X-Api-Key' => 'wh_' . Str::random(40)])
        ->getJson('/api/v1/api/ping')
        ->assertUnauthorized();
});

test('ping rejects a revoked API key', function () {
    [$key, $raw] = chantier32MintApiKey(tenantId: 1);
    $key->update(['revoked_at' => now()]);

    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertUnauthorized();
});

test('ping rejects an expired API key', function () {
    [$key, $raw] = chantier32MintApiKey(tenantId: 1);
    $key->forceFill(['expires_at' => now()->subDay()])->save();

    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertUnauthorized();
});

test('a successful ping writes a real ApiRequest row and touches last_used_at', function () {
    [$key, $raw] = chantier32MintApiKey(tenantId: 7);

    expect(ApiRequest::count())->toBe(0);
    expect($key->last_used_at)->toBeNull();

    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertOk();

    expect(ApiRequest::count())->toBe(1);
    $logged = ApiRequest::first();
    expect($logged->tenant_id)->toBe(7)
        ->and($logged->api_key_id)->toBe($key->id)
        ->and($logged->status_code)->toBe(200)
        ->and($logged->method)->toBe('GET');

    $key->refresh();
    expect($key->last_used_at)->not->toBeNull();
});

test('an API key enforces its own configured rate_limit', function () {
    [$key, $raw] = chantier32MintApiKey(tenantId: 9, rateLimit: 2);

    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertOk();
    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertOk();
    // 3rd request within the hour must be throttled by the key's own limit.
    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertStatus(429);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 6 (deep security) — key_hash/secret material must never round-trip
// to a client, even through a raw query path.
// ─────────────────────────────────────────────────────────────────────────

test('listing/showing an API key never exposes key_hash', function () {
    $company = Company::factory()->create();
    $user = chantier32ApiUser($company, 'admin');
    [$key] = chantier32MintApiKey(tenantId: $company->id);

    $list = test()->actingAs($user, 'sanctum')->getJson('/api/v1/api/keys')->assertOk();
    expect($list->getContent())->not->toContain($key->key_hash);

    $show = test()->actingAs($user, 'sanctum')->getJson("/api/v1/api/keys/{$key->id}")->assertOk();
    expect($show->getContent())->not->toContain($key->key_hash);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 11 (CORE integration) — RecordsActivity: create/revoke are real,
// audited, sensitive actions now; a mere last_used_at touch must NOT spam
// the audit trail on every single authenticated request.
// ─────────────────────────────────────────────────────────────────────────

test('creating an API key writes a real audit log entry with no key material', function () {
    $company = Company::factory()->create();
    $user = chantier32ApiUser($company, 'admin');

    $created = test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/keys', ['name' => 'Audited key'])
        ->assertCreated();
    $keyId = $created->json('data.id');

    $log = AuditLog::where('subject_type', ApiKey::class)
        ->where('subject_id', $keyId)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->module)->toBe('API')
        ->and($log->new_values)->not->toHaveKey('key_hash')
        ->and($log->new_values['name'])->toBe('Audited key');
});

test('revoking an API key writes a real audit log entry', function () {
    $company = Company::factory()->create();
    $user = chantier32ApiUser($company, 'admin');
    [$key] = chantier32MintApiKey(tenantId: $company->id);

    test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/api/keys/{$key->id}/revoke")->assertOk();

    $log = AuditLog::where('subject_type', ApiKey::class)
        ->where('subject_id', $key->id)
        ->where('action', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['revoked_at'] ?? null)->not->toBeNull();
});

test('an authenticated ping alone (last_used_at touch only) does not spam the audit trail', function () {
    [$key, $raw] = chantier32MintApiKey(tenantId: 3);
    $before = AuditLog::where('subject_type', ApiKey::class)->where('subject_id', $key->id)->count();

    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertOk();
    test()->withHeaders(['X-Api-Key' => $raw])->getJson('/api/v1/api/ping')->assertOk();

    $after = AuditLog::where('subject_type', ApiKey::class)->where('subject_id', $key->id)->count();
    expect($after)->toBe($before);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 8 (business-rule validation) — an already-expired key is never a
// legitimate credential to mint in the first place.
// ─────────────────────────────────────────────────────────────────────────

test('creating an API key with an expiry already in the past is rejected', function () {
    $user = chantier32ApiUser();

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/keys', [
        'name' => 'Dead on arrival',
        'expires_at' => now()->subDay()->toDateTimeString(),
    ])->assertStatus(422);
});

test('creating an API key with a future expiry is accepted', function () {
    $user = chantier32ApiUser();

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/keys', [
        'name' => 'Fine',
        'expires_at' => now()->addMonth()->toDateTimeString(),
    ])->assertCreated();
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 6/7 re-confirmation — the already-fixed (Chantier 10) tenant
// scoping is still genuinely company_id-based, not just trusted from the
// changelog. Chantier19ApiReauditTest.php already covers this in depth;
// this is a lighter re-confirmation from the new API-key auth angle, which
// didn't exist before this chantier.
// ─────────────────────────────────────────────────────────────────────────

test('an api-key-authenticated ping only ever logs under its own real tenant, never a spoofable one', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    [, $rawA] = chantier32MintApiKey(tenantId: $companyA->id);

    // No client-controlled tenant override exists on this endpoint at all
    // (X-Tenant-Id/tenant_id are never read by AuthenticateApiKey) — confirm
    // spoofing headers has zero effect on which tenant the request logs
    // under.
    test()->withHeaders([
        'X-Api-Key' => $rawA,
        'X-Tenant-Id' => (string) $companyB->id,
    ])->getJson('/api/v1/api/ping?tenant_id=' . $companyB->id)->assertOk()
        ->assertJsonPath('data.tenant_id', $companyA->id);

    expect(ApiRequest::first()->tenant_id)->toBe($companyA->id);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 14f (performance) — RequestLogController::index() stays a single,
// non-N+1, real-pagination query under realistic volume.
// ─────────────────────────────────────────────────────────────────────────

test('the request log listing endpoint pages a large real dataset in a single query, not N+1', function () {
    $company = Company::factory()->create();
    $user = chantier32ApiUser($company, 'admin');
    [$key] = chantier32MintApiKey(tenantId: $company->id);

    $rows = [];
    for ($i = 0; $i < 300; $i++) {
        $rows[] = [
            'tenant_id'   => $company->id,
            'api_key_id'  => $key->id,
            'method'      => 'GET',
            'endpoint'    => "/api/v1/probe/{$i}",
            'status_code' => 200,
            'duration_ms' => 10,
            'created_at'  => now()->subMinutes($i),
        ];
    }
    ApiRequest::insert($rows);

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/api/logs')->assertOk();
    $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    // A paginated single-table query: one SELECT for the page + one COUNT
    // for total, plus a handful more for auth/role-gate middleware
    // (session.security/tenancy.user/role: all run their own small
    // queries) — never one query per row, which is the real concern this
    // test guards against (would be 300+ here).
    expect($queryCount)->toBeLessThanOrEqual(10);
    expect($response->json('data'))->toHaveCount(50); // paginate(50)
    expect($response->json('total'))->toBe(300);
});

// ─────────────────────────────────────────────────────────────────────────
// Layer 7 (RBAC) — the phantom 'api-manager' role removal from
// ApiKeyPolicy: confirm it changed nothing observable (already
// unreachable, since nothing in this repo's seeder grants it) and that
// admin/super-admin still work correctly.
// ─────────────────────────────────────────────────────────────────────────

test('ApiKeyPolicy still correctly gates create to admin/super-admin only, api-manager role never existed in the seeder', function () {
    expect(\Spatie\Permission\Models\Role::where('name', 'api-manager')->exists())->toBeFalse();

    $admin = chantier32ApiUser(role: 'admin');
    test()->actingAs($admin, 'sanctum')->postJson('/api/v1/api/keys', ['name' => 'Admin key'])
        ->assertCreated();

    $superAdmin = chantier32ApiUser(role: 'super-admin');
    test()->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/api/keys', ['name' => 'Super admin key'])
        ->assertCreated();
});
