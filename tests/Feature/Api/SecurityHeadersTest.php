<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('api responses include security headers', function () {
    $response = $this->getJson('/api/health');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
});

test('web responses include Content-Security-Policy header', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    $csp = $response->headers->get('Content-Security-Policy', '');
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("frame-src 'none'");
    expect($csp)->toContain("object-src 'none'");
});

test('CSP nonce changes per request', function () {
    $r1 = $this->get('/login');
    $r2 = $this->get('/login');

    $csp1 = $r1->headers->get('Content-Security-Policy', '');
    $csp2 = $r2->headers->get('Content-Security-Policy', '');

    preg_match("/nonce-([A-Za-z0-9+\/=]+)/", $csp1, $m1);
    preg_match("/nonce-([A-Za-z0-9+\/=]+)/", $csp2, $m2);

    // Both responses must have a nonce
    expect($m1)->toHaveKey(1);
    expect($m2)->toHaveKey(1);

    // Nonces must differ between requests
    expect($m1[1])->not->toBe($m2[1]);
});

test('api responses do not include Content-Security-Policy', function () {
    $response = $this->getJson('/api/health');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

test('auth endpoints are rate limited', function () {
    // Exhaust the auth limiter (10 per minute by default in tests)
    $payload = ['email' => 'nobody@example.com', 'password' => 'wrong'];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/login', $payload);
    }

    $response = $this->postJson('/api/v1/auth/login', $payload);
    $response->assertStatus(429);
});
