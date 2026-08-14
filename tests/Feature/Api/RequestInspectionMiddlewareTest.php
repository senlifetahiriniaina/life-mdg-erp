<?php

declare(strict_types=1);

use App\Http\Middleware\RequestInspectionMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Security\Models\ThreatIndicator;

uses(RefreshDatabase::class);

function registerWafTestRoute(): void
{
    Route::middleware(['web', RequestInspectionMiddleware::class])
        ->any('/__waf-test', fn () => response()->json(['ok' => true]));
}

test('shadow mode logs an XSS match but does not block the request', function () {
    config(['security.request_inspection.shadow_mode' => true]);
    registerWafTestRoute();

    Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
    // XssPreventionService::detectXss() also logs its own 'XSS pattern
    // detected' warning directly (not via channel()) — both calls share
    // this expectation since both messages contain that substring.
    Log::shouldReceive('warning')
        ->atLeast()->once()
        ->withArgs(fn ($message) => str_contains($message, 'XSS pattern detected'));

    $response = $this->postJson('/__waf-test', ['comment' => '<script>alert(1)</script>']);

    $response->assertOk();
});

test('enforce mode blocks a request with an XSS payload', function () {
    config(['security.request_inspection.shadow_mode' => false]);
    registerWafTestRoute();

    $response = $this->postJson('/__waf-test', ['comment' => '<script>alert(1)</script>']);

    $response->assertStatus(422);
});

test('a clean request passes through untouched in enforce mode', function () {
    config(['security.request_inspection.shadow_mode' => false]);
    registerWafTestRoute();

    $response = $this->postJson('/__waf-test', ['comment' => 'This invoice looks correct, please approve.']);

    $response->assertOk();
});

test('excluded fields are never scanned for XSS', function () {
    config([
        'security.request_inspection.shadow_mode' => false,
        'security.request_inspection.excluded_fields' => ['password'],
    ]);
    registerWafTestRoute();

    $response = $this->postJson('/__waf-test', ['password' => '<script>irrelevant-but-excluded</script>']);

    $response->assertOk();
});

test('enforce mode blocks a request from a known threat IP', function () {
    config(['security.request_inspection.shadow_mode' => false]);
    registerWafTestRoute();

    ThreatIndicator::create([
        'indicator_type' => 'ip',
        'indicator_value' => '203.0.113.66',
        'threat_level' => 'high',
        'source' => 'test',
        'is_whitelisted' => false,
        'detected_at' => now(),
    ]);

    $response = $this->call('GET', '/__waf-test', [], [], [], ['REMOTE_ADDR' => '203.0.113.66']);

    $response->assertStatus(403);
});

test('shadow mode does not block a known threat IP', function () {
    config(['security.request_inspection.shadow_mode' => true]);
    registerWafTestRoute();

    ThreatIndicator::create([
        'indicator_type' => 'ip',
        'indicator_value' => '203.0.113.77',
        'threat_level' => 'high',
        'source' => 'test',
        'is_whitelisted' => false,
        'detected_at' => now(),
    ]);

    $response = $this->call('GET', '/__waf-test', [], [], [], ['REMOTE_ADDR' => '203.0.113.77']);

    $response->assertOk();
});
