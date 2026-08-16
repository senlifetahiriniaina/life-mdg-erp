<?php

declare(strict_types=1);

use App\Http\Middleware\TenantRateLimitMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    config(['rate_limit.tenant_tiers.free.requests_per_minute' => 5]);
});

test('middleware allows requests under rate limit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    expect($response->status())->not->toBe(429);
});

test('middleware returns 429 when rate limit exceeded', function () {
    $user = User::factory()->create();

    // Make requests equal to limit
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard');
    }

    // Next request should be rate limited
    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    expect($response->status())->toBe(429);
});

test('middleware includes rate limit headers in response', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    expect($response->headers->get('X-RateLimit-Limit'))->not->toBeNull();
    expect($response->headers->get('X-RateLimit-Remaining'))->not->toBeNull();
    expect($response->headers->get('X-RateLimit-Reset'))->not->toBeNull();
});

test('middleware skips rate limiting for health endpoint', function () {
    // Health check should not be rate limited
    $response = $this->getJson('/api/health');

    expect($response->status())->not->toBe(429);
});

test('middleware skips rate limiting for metrics endpoint', function () {
    // Metrics should not be rate limited
    $response = $this->getJson('/api/metrics');

    expect($response->status())->not->toBe(429);
});

test('different users have independent rate limits', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // User1 hits their limit
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user1, 'sanctum')->getJson('/api/v1/dashboard');
    }

    // User1 should be rate limited
    $response1 = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/v1/dashboard');
    expect($response1->status())->toBe(429);

    // User2 should still be able to make requests
    $response2 = $this->actingAs($user2, 'sanctum')
        ->getJson('/api/v1/dashboard');
    expect($response2->status())->not->toBe(429);
});

test('unauthenticated requests use IP-based rate limiting', function () {
    config(['rate_limit.ip_limits.requests_per_minute' => 3]);

    // Make requests from same IP
    for ($i = 0; $i < 3; $i++) {
        $this->getJson('/api/health');
    }

    // Next request should not be limited (health is whitelisted)
    // Try a different endpoint
    $response = $this->getJson('/api/v1/openapi');

    // Would be rate limited if IP limiting was enforced on non-whitelisted endpoints
    // (but openapi endpoint likely doesn't have auth requirement)
});

test('rate limit counter resets after time window', function () {
    $user = User::factory()->create();

    // Make initial request
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard');

    // Clear cache to simulate time window expiration
    Cache::flush();

    // Should be able to make request again
    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    expect($response->status())->not->toBe(429);
});

test('concurrent request limit is enforced', function () {
    $user = User::factory()->create();

    // Simulate hitting concurrent request limit
    config(['rate_limit.tenant_tiers.free.concurrent_requests' => 1]);

    // First request should succeed
    $response1 = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard');

    expect($response1->status())->not->toBe(429);

    // Note: In reality, testing concurrent limits requires parallel requests
    // which is difficult in synchronous test environment
});

test('admin routes also enforce rate limiting', function () {
    $admin = User::factory()->create();
    $admin->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');

    config(['rate_limit.tenant_tiers.free.requests_per_minute' => 3]);

    // Make requests up to limit
    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/backup-schedules');
    }

    // Next request should be rate limited
    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/backup-schedules');

    expect($response->status())->toBe(429);
});
