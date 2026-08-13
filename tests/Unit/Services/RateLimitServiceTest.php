<?php

declare(strict_types=1);

use App\Services\RateLimitService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->rateLimitService = app(RateLimitService::class);
});

test('free tier rate limits configuration is correct', function () {
    $limits = $this->rateLimitService->getTenantLimits(1, 'free');

    expect($limits)
        ->toHaveKey('requests_per_minute', 60)
        ->toHaveKey('requests_per_hour', 3000)
        ->toHaveKey('concurrent_requests', 5);
});

test('enterprise tier rate limits configuration is correct', function () {
    $limits = $this->rateLimitService->getTenantLimits(1, 'enterprise');

    expect($limits)
        ->toHaveKey('requests_per_minute', 2000)
        ->toHaveKey('requests_per_hour', 120000)
        ->toHaveKey('concurrent_requests', 200);
});

test('unknown tier defaults to free tier', function () {
    $limits = $this->rateLimitService->getTenantLimits(1, 'unknown-tier');

    expect($limits)
        ->toHaveKey('requests_per_minute', 60)
        ->toHaveKey('requests_per_hour', 3000);
});

test('check tenant limit allows requests under limit', function () {
    $result = $this->rateLimitService->checkTenantLimit(1, 1, 'free');

    expect($result)
        ->toBeArray()
        ->toHaveKey('limited', false)
        ->toHaveKey('remaining')
        ->toHaveKey('limit', 60);
});

test('check tenant limit rejects requests over limit', function () {
    // Simulate hitting the limit
    $limiter = app(\Illuminate\Cache\RateLimiter::class);
    $key = 'rate_limit:tenant:1:user:1';

    // Hit the limit 61 times
    for ($i = 0; $i < 61; $i++) {
        $limiter->hit($key, 60);
    }

    $result = $this->rateLimitService->checkTenantLimit(1, 1, 'free');

    expect($result)
        ->toBeArray()
        ->toHaveKey('limited', true)
        ->toHaveKey('retry_after');
});

test('check hourly limit works correctly', function () {
    $result = $this->rateLimitService->checkHourlyLimit(1, 1, 'free');

    expect($result)
        ->toBeArray()
        ->toHaveKey('limited', false)
        ->toHaveKey('limit', 3000);
});

test('check concurrent limit allows under limit', function () {
    $allowed = $this->rateLimitService->checkConcurrentLimit(1, 1, 'free');

    expect($allowed)->toBeTrue();
});

test('check concurrent limit rejects over limit', function () {
    Cache::put('rate_limit:concurrent:tenant:1:user:1', 5, 60);

    $allowed = $this->rateLimitService->checkConcurrentLimit(1, 1, 'free');

    expect($allowed)->toBeFalse();
});

test('check ip limit allows requests under limit', function () {
    $result = $this->rateLimitService->checkIpLimit('127.0.0.1');

    expect($result)
        ->toBeArray()
        ->toHaveKey('limited', false)
        ->toHaveKey('limit', 100);
});

test('reset limit clears cache', function () {
    $this->rateLimitService->checkTenantLimit(1, 1, 'free');

    $this->rateLimitService->resetLimit(1, 1);

    $stats = $this->rateLimitService->getStats(1, 1);
    expect($stats['current_minute_requests'])->toBe(0);
});

test('get stats returns current limit information', function () {
    $this->rateLimitService->checkTenantLimit(1, 1, 'free');

    $stats = $this->rateLimitService->getStats(1, 1);

    expect($stats)
        ->toHaveKey('tenant_id', 1)
        ->toHaveKey('user_id', 1)
        ->toHaveKey('current_minute_requests')
        ->toHaveKey('current_hour_requests')
        ->toHaveKey('concurrent_requests');
});

test('whitelist ip skips rate limiting', function () {
    config(['rate_limit.ip_limits.whitelist' => '192.168.1.1']);

    $result = $this->rateLimitService->checkIpLimit('192.168.1.1');

    expect($result)
        ->toBeArray()
        ->toHaveKey('limited', false);
});

test('decrement concurrent limit decreases counter', function () {
    $this->rateLimitService->checkConcurrentLimit(1, 1, 'free');

    $statsBefore = $this->rateLimitService->getStats(1, 1);
    $this->rateLimitService->decrementConcurrentLimit(1, 1);
    $statsAfter = $this->rateLimitService->getStats(1, 1);

    expect($statsAfter['concurrent_requests'])
        ->toBeLessThan($statsBefore['concurrent_requests']);
});
