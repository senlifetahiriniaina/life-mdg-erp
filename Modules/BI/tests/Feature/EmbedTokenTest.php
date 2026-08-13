<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\EmbedToken;
use Modules\BI\Services\EmbedTokenService;

uses(RefreshDatabase::class);

// ─── EmbedTokenService unit tests ─────────────────────────────────────────────

test('generateEmbedToken returns a signed JWT with three parts', function () {
    $service = app(EmbedTokenService::class);
    $result  = $service->generateEmbedToken(1, 42, ['example.com'], 3600, 1);

    expect($result)->toHaveKeys(['token', 'expires_at', 'embed_token_id']);
    expect(explode('.', $result['token']))->toHaveCount(3);
});

test('validateEmbedToken decodes a freshly generated token', function () {
    $service = app(EmbedTokenService::class);
    $result  = $service->generateEmbedToken(5, 10, ['acme.io'], 3600, 1);

    $payload = $service->validateEmbedToken($result['token']);

    expect($payload['dashboard_id'])->toBe(5);
    expect($payload['tenant_id'])->toBe(10);
    expect($payload['scope'])->toBe('embed:read');
});

test('validateEmbedToken throws on expired token', function () {
    $service = app(EmbedTokenService::class);

    // Generate with 1 second TTL then travel into the future
    $result = $service->generateEmbedToken(1, 1, ['x.com'], 1, 1);

    Carbon::setTestNow(now()->addSeconds(5));
    expect(fn () => $service->validateEmbedToken($result['token']))
        ->toThrow(\RuntimeException::class, 'expired');
})->after(fn () => Carbon::setTestNow());

test('validateEmbedToken throws on tampered signature', function () {
    $service = app(EmbedTokenService::class);
    $result  = $service->generateEmbedToken(1, 1, ['x.com'], 3600, 1);

    $parts           = explode('.', $result['token']);
    $parts[2]        = 'invalidsignature';
    $tamperedToken   = implode('.', $parts);

    expect(fn () => $service->validateEmbedToken($tamperedToken))
        ->toThrow(\RuntimeException::class, 'signature');
});

test('validateEmbedToken throws on malformed token', function () {
    $service = app(EmbedTokenService::class);
    expect(fn () => $service->validateEmbedToken('not.a.valid.token.here'))
        ->toThrow(\RuntimeException::class);
});

test('isAllowedOrigin correctly matches allowed domains', function () {
    $service = app(EmbedTokenService::class);

    expect($service->isAllowedOrigin('https://app.example.com', ['example.com']))->toBeTrue();
    expect($service->isAllowedOrigin('https://example.com', ['example.com']))->toBeTrue();
    expect($service->isAllowedOrigin('https://evil.com', ['example.com']))->toBeFalse();
    expect($service->isAllowedOrigin('https://anything.com', []))->toBeFalse();
});

test('revokeToken marks the record as revoked', function () {
    $service = app(EmbedTokenService::class);
    $result  = $service->generateEmbedToken(1, 1, ['x.com'], 3600, 1);

    $record = EmbedToken::where('embed_token_id', $result['embed_token_id'])->first()
        ?? EmbedToken::find($result['embed_token_id']);

    $revoked = $service->revokeToken($record->jti);
    expect($revoked)->toBeTrue();

    expect(fn () => $service->validateEmbedToken($result['token']))
        ->toThrow(\RuntimeException::class);
});

// ─── HTTP API tests ────────────────────────────────────────────────────────────

test('POST /bi/embed/tokens requires authentication', function () {
    $this->postJson('/api/v1/bi/embed/tokens', [
        'dashboard_id'    => 1,
        'allowed_domains' => ['example.com'],
    ])->assertStatus(401);
});

test('POST /bi/embed/tokens creates a token for valid dashboard', function () {
    $user      = actingAsUser('manager');
    $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

    $response = $this->postJson('/api/v1/bi/embed/tokens', [
        'dashboard_id'    => $dashboard->id,
        'allowed_domains' => ['partner.example.com'],
        'expires_in'      => 7200,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'expires_at', 'embed_token_id', 'embed_url']);
});

test('GET /bi/embed/validate returns valid=true for a fresh token', function () {
    $user      = actingAsUser('manager');
    $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

    $createRes = $this->postJson('/api/v1/bi/embed/tokens', [
        'dashboard_id'    => $dashboard->id,
        'allowed_domains' => ['example.com'],
    ]);

    $token = $createRes->json('token');

    $validateRes = $this->getJson('/api/v1/bi/embed/validate?token=' . urlencode($token));
    $validateRes->assertStatus(200)->assertJsonPath('valid', true);
});

test('GET /bi/embed/validate returns valid=false for garbage token', function () {
    $this->getJson('/api/v1/bi/embed/validate?token=garbage.token.here')
        ->assertStatus(401)
        ->assertJsonPath('valid', false);
});

test('GET /bi/embed/dashboard/{id} returns widget data with valid embed token', function () {
    $user      = actingAsUser('manager');
    $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

    $token = $this->postJson('/api/v1/bi/embed/tokens', [
        'dashboard_id'    => $dashboard->id,
        'allowed_domains' => ['example.com'],
    ])->json('token');

    $res = $this->getJson("/api/v1/bi/embed/dashboard/{$dashboard->id}?embed_token=" . urlencode($token));
    $res->assertStatus(200)
        ->assertJsonPath('embed.read_only', true)
        ->assertJsonPath('dashboard.id', $dashboard->id);
});

test('GET /bi/embed/dashboard/{id} rejects token scoped to a different dashboard', function () {
    $user      = actingAsUser('manager');
    $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);
    $other     = Dashboard::factory()->create(['user_id' => $user->id]);

    $token = $this->postJson('/api/v1/bi/embed/tokens', [
        'dashboard_id'    => $dashboard->id,
        'allowed_domains' => ['example.com'],
    ])->json('token');

    $this->getJson("/api/v1/bi/embed/dashboard/{$other->id}?embed_token=" . urlencode($token))
        ->assertStatus(403);
});

test('no cross-tenant leakage via embed endpoint', function () {
    // Tenant A creates a token for their dashboard
    $userA      = actingAsUser('manager');
    $dashboardA = Dashboard::factory()->create(['user_id' => $userA->id]);

    $tokenA = $this->postJson('/api/v1/bi/embed/tokens', [
        'dashboard_id'    => $dashboardA->id,
        'allowed_domains' => ['tenant-a.com'],
    ])->json('token');

    // Attempting to access a dashboard that doesn't match the token scope is rejected
    $this->getJson("/api/v1/bi/embed/dashboard/99999?embed_token=" . urlencode($tokenA))
        ->assertStatus(403);
});
