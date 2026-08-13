<?php

test('cors config never allows wildcard origin with credentials', function () {
    $origins = config('cors.allowed_origins');

    expect($origins)->toBeArray()
        ->and($origins)->not->toContain('*')
        ->and(config('cors.supports_credentials'))->toBeTrue();
});

test('cors allowed origins are driven by env', function () {
    config()->set('cors.allowed_origins', ['https://app.widehalo.com']);

    expect(config('cors.allowed_origins'))->toBe(['https://app.widehalo.com']);
});

test('a disallowed origin does not receive an allow-origin header', function () {
    config()->set('cors.allowed_origins', ['https://app.widehalo.com']);

    $response = $this->call('OPTIONS', '/api/health', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com');
});
