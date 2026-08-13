<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Integration\Services\SupabaseService;

// ---------------------------------------------------------------------------
// Test 1 — isConfigured() returns false when env vars are missing
// ---------------------------------------------------------------------------

test('isConfigured returns false when SUPABASE_URL is empty', function () {
    config(['supabase.url' => '', 'supabase.service_key' => '']);

    $service = new SupabaseService();
    expect($service->isConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Test 2 — isConfigured() returns false when service_key is missing
// ---------------------------------------------------------------------------

test('isConfigured returns false when service_key is empty', function () {
    config(['supabase.url' => 'https://xyzabc.supabase.co', 'supabase.service_key' => '']);

    $service = new SupabaseService();
    expect($service->isConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Test 3 — isConfigured() returns true when both url and service_key are set
// ---------------------------------------------------------------------------

test('isConfigured returns true when url and service_key are configured', function () {
    config(['supabase.url' => 'https://xyzabc.supabase.co', 'supabase.service_key' => 'service-role-key-abc']);

    $service = new SupabaseService();
    expect($service->isConfigured())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Test 4 — from() builds correct PostgREST URL and returns data
// ---------------------------------------------------------------------------

test('from() queries the correct PostgREST endpoint', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'service-key',
        'supabase.anon_key'    => 'anon-key',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/contacts*' => Http::response(
            [['id' => 1, 'name' => 'Alice']],
            200
        ),
    ]);

    $service = new SupabaseService();
    $result = $service->from('contacts');

    expect($result['error'])->toBeNull()
        ->and($result['data'])->toBeArray()
        ->and($result['data'][0]['name'])->toBe('Alice');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/rest/v1/contacts'));
});

// ---------------------------------------------------------------------------
// Test 5 — from() returns error on HTTP failure
// ---------------------------------------------------------------------------

test('from() returns error array when request fails', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'service-key',
        'supabase.anon_key'    => 'anon-key',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/*' => Http::response('Unauthorized', 401),
    ]);

    $service = new SupabaseService();
    $result = $service->from('orders');

    expect($result['data'])->toBe([])
        ->and($result['error'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 6 — insert() sends POST with correct headers and returns data
// ---------------------------------------------------------------------------

test('insert() sends POST to PostgREST table endpoint', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc-key',
        'supabase.anon_key'    => 'anon-key',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/products*' => Http::response(
            [['id' => 5, 'name' => 'Widget']],
            201
        ),
    ]);

    $service = new SupabaseService();
    $result = $service->insert('products', ['name' => 'Widget', 'price' => 1500]);

    expect($result['error'])->toBeNull()
        ->and($result['data'])->toBeArray();

    Http::assertSent(function ($req) {
        return $req->method() === 'POST'
            && str_contains($req->url(), '/rest/v1/products');
    });
});

// ---------------------------------------------------------------------------
// Test 7 — headers() includes apikey and Authorization bearer
// ---------------------------------------------------------------------------

test('requests include apikey and Authorization headers', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'my-service-key',
        'supabase.anon_key'    => 'anon-key',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/*' => Http::response([], 200),
    ]);

    $service = new SupabaseService();
    $service->from('test_table');

    Http::assertSent(function ($req) {
        return $req->hasHeader('apikey')
            && $req->hasHeader('Authorization')
            && str_starts_with($req->header('Authorization')[0], 'Bearer ');
    });
});

// ---------------------------------------------------------------------------
// Test 8 — getRealtimeConfig() returns expected keys
// ---------------------------------------------------------------------------

test('getRealtimeConfig() returns url, anon_key, channel and enabled keys', function () {
    config([
        'supabase.url'               => 'https://abc.supabase.co',
        'supabase.service_key'       => 'svc',
        'supabase.anon_key'          => 'anon-123',
        'supabase.realtime.enabled'  => true,
        'supabase.realtime.channel'  => 'erp-channel',
    ]);

    $service = new SupabaseService();
    $config = $service->getRealtimeConfig();

    expect($config)->toHaveKeys(['url', 'anon_key', 'channel', 'enabled'])
        ->and($config['url'])->toBe('https://abc.supabase.co')
        ->and($config['anon_key'])->toBe('anon-123')
        ->and($config['channel'])->toBe('erp-channel')
        ->and($config['enabled'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// Test 9 — uploadFile() constructs correct Storage URL
// ---------------------------------------------------------------------------

test('uploadFile() posts to the Supabase Storage endpoint', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc-key',
        'supabase.anon_key'    => 'anon-key',
    ]);

    Http::fake([
        'https://test.supabase.co/storage/v1/object/*' => Http::response(['Key' => 'invoices/doc.pdf'], 200),
    ]);

    $service = new SupabaseService();
    $result = $service->uploadFile('invoices', 'doc.pdf', '%PDF-1.4 contents');

    expect($result['error'])->toBeNull()
        ->and($result['path'])->toBe('doc.pdf');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/storage/v1/object/invoices/doc.pdf'));
});

// ---------------------------------------------------------------------------
// Test 10 — getPublicUrl() returns expected format
// ---------------------------------------------------------------------------

test('getPublicUrl() returns correct public URL format', function () {
    config([
        'supabase.url'         => 'https://myproject.supabase.co',
        'supabase.service_key' => 'key',
        'supabase.anon_key'    => 'anon',
    ]);

    $service = new SupabaseService();
    $url = $service->getPublicUrl('my-bucket', 'uploads/photo.jpg');

    expect($url)->toBe('https://myproject.supabase.co/storage/v1/object/public/my-bucket/uploads/photo.jpg');
});

// ---------------------------------------------------------------------------
// Test 11 — update() sends PATCH to correct filtered URL
// ---------------------------------------------------------------------------

test('update() sends PATCH request with PostgREST filter query string', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc',
        'supabase.anon_key'    => 'anon',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/invoices*' => Http::response([['id' => 1]], 200),
    ]);

    $service = new SupabaseService();
    $result = $service->update('invoices', ['status' => 'paid'], ['id' => 1]);

    expect($result['error'])->toBeNull();

    Http::assertSent(function ($req) {
        return $req->method() === 'PATCH'
            && str_contains($req->url(), '/rest/v1/invoices')
            && str_contains($req->url(), 'id=eq.1');
    });
});

// ---------------------------------------------------------------------------
// Test 12 — delete() sends DELETE to correct filtered URL
// ---------------------------------------------------------------------------

test('delete() sends DELETE request with PostgREST filter query string', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc',
        'supabase.anon_key'    => 'anon',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/sessions*' => Http::response(null, 204),
    ]);

    $service = new SupabaseService();
    $result = $service->delete('sessions', ['user_id' => 42]);

    expect($result['success'])->toBeTrue()
        ->and($result['error'])->toBeNull();

    Http::assertSent(function ($req) {
        return $req->method() === 'DELETE'
            && str_contains($req->url(), '/rest/v1/sessions')
            && str_contains($req->url(), 'user_id=eq.42');
    });
});

// ---------------------------------------------------------------------------
// Test 13 — delete() returns error on HTTP failure
// ---------------------------------------------------------------------------

test('delete() returns success=false when request fails', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc',
        'supabase.anon_key'    => 'anon',
    ]);

    Http::fake([
        'https://test.supabase.co/rest/v1/*' => Http::response('Forbidden', 403),
    ]);

    $service = new SupabaseService();
    $result = $service->delete('restricted_table', ['id' => 99]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 14 — uploadFile() returns error when storage endpoint fails
// ---------------------------------------------------------------------------

test('uploadFile() returns error when Supabase Storage rejects the upload', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc',
        'supabase.anon_key'    => 'anon',
    ]);

    Http::fake([
        'https://test.supabase.co/storage/v1/object/*' => Http::response('Storage Error', 500),
    ]);

    $service = new SupabaseService();
    $result = $service->uploadFile('bucket', 'file.txt', 'content');

    expect($result['path'])->toBeNull()
        ->and($result['url'])->toBeNull()
        ->and($result['error'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 15 — All methods degrade gracefully via Http::fake()
// ---------------------------------------------------------------------------

test('all service methods return graceful error structures without throwing', function () {
    config([
        'supabase.url'         => 'https://test.supabase.co',
        'supabase.service_key' => 'svc',
        'supabase.anon_key'    => 'anon',
    ]);

    Http::fake([
        '*' => Http::response('Service Unavailable', 503),
    ]);

    $service = new SupabaseService();

    $from   = $service->from('any_table');
    $insert = $service->insert('any_table', ['key' => 'val']);
    $update = $service->update('any_table', ['key' => 'val'], ['id' => 1]);
    $delete = $service->delete('any_table', ['id' => 1]);
    $upload = $service->uploadFile('bucket', 'file.txt', 'data');

    expect($from['data'])->toBe([])
        ->and($insert['data'])->toBeNull()
        ->and($update['data'])->toBeNull()
        ->and($delete['success'])->toBeFalse()
        ->and($upload['path'])->toBeNull();
});
