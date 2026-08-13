<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Integration\Services\FirebaseService;

// ---------------------------------------------------------------------------
// Test 1 — isConfigured() returns false when project_id is missing
// ---------------------------------------------------------------------------

test('isConfigured returns false when FIREBASE_PROJECT_ID is empty', function () {
    config(['firebase.project_id' => '']);

    $service = new FirebaseService();
    expect($service->isConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Test 2 — isConfigured() returns true when project_id is set
// ---------------------------------------------------------------------------

test('isConfigured returns true when project_id is configured', function () {
    config(['firebase.project_id' => 'widehalo-prod', 'firebase.database_url' => 'https://widehalo-prod.firebaseio.com']);

    $service = new FirebaseService();
    expect($service->isConfigured())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Test 3 — sendPushNotification() sends to the correct FCM endpoint
// ---------------------------------------------------------------------------

test('sendPushNotification() posts to the FCM send endpoint', function () {
    config([
        'firebase.project_id'   => 'my-project',
        'firebase.database_url' => 'https://my-project.firebaseio.com',
        'firebase.credentials'  => '',
    ]);

    Http::fake([
        'https://fcm.googleapis.com/fcm/send' => Http::response(['success' => 1], 200),
    ]);

    $service = new FirebaseService();
    $result = $service->sendPushNotification('device-token-abc', 'Hello', 'Test message');

    expect($result)->toBeTrue();

    Http::assertSent(function ($req) {
        return str_contains($req->url(), 'fcm.googleapis.com/fcm/send')
            && $req->method() === 'POST';
    });
});

// ---------------------------------------------------------------------------
// Test 4 — sendPushNotification() returns false on FCM failure
// ---------------------------------------------------------------------------

test('sendPushNotification() returns false when FCM responds with error', function () {
    config([
        'firebase.project_id'   => 'my-project',
        'firebase.database_url' => 'https://my-project.firebaseio.com',
        'firebase.credentials'  => '',
    ]);

    Http::fake([
        'https://fcm.googleapis.com/fcm/send' => Http::response(['failure' => 1, 'success' => 0], 200),
    ]);

    $service = new FirebaseService();
    $result = $service->sendPushNotification('bad-token', 'Title', 'Body');

    expect($result)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Test 5 — set() constructs the correct Firebase Realtime Database URL
// ---------------------------------------------------------------------------

test('set() sends PUT to the correct Firebase Database path', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/notifications/1.json' => Http::response(['status' => 'ok'], 200),
    ]);

    $service = new FirebaseService();
    $result = $service->set('notifications/1', ['status' => 'ok']);

    expect($result['error'])->toBeNull()
        ->and($result['data'])->toBeArray();

    Http::assertSent(function ($req) {
        return $req->method() === 'PUT'
            && str_contains($req->url(), '/notifications/1.json');
    });
});

// ---------------------------------------------------------------------------
// Test 6 — get() parses the Firebase REST response correctly
// ---------------------------------------------------------------------------

test('get() retrieves data from the Firebase Realtime Database path', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/orders/42.json' => Http::response(
            ['id' => 42, 'total' => 15000],
            200
        ),
    ]);

    $service = new FirebaseService();
    $result = $service->get('orders/42');

    expect($result['error'])->toBeNull()
        ->and($result['data']['id'])->toBe(42)
        ->and($result['data']['total'])->toBe(15000);
});

// ---------------------------------------------------------------------------
// Test 7 — get() returns error structure on HTTP failure
// ---------------------------------------------------------------------------

test('get() returns error structure when Firebase REST request fails', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/*' => Http::response('Unauthorized', 401),
    ]);

    $service = new FirebaseService();
    $result = $service->get('protected/path');

    expect($result['data'])->toBeNull()
        ->and($result['error'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 8 — push() sends POST and returns the generated child key
// ---------------------------------------------------------------------------

test('push() generates a new child key from Firebase POST response', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/events.json' => Http::response(
            ['name' => '-NxYz12345ABC'],
            200
        ),
    ]);

    $service = new FirebaseService();
    $key = $service->push('events', ['type' => 'order.created', 'order_id' => 7]);

    expect($key)->toBe('-NxYz12345ABC');

    Http::assertSent(function ($req) {
        return $req->method() === 'POST'
            && str_contains($req->url(), '/events.json');
    });
});

// ---------------------------------------------------------------------------
// Test 9 — push() returns empty string on failure
// ---------------------------------------------------------------------------

test('push() returns empty string when Firebase rejects the request', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/*' => Http::response('Error', 500),
    ]);

    $service = new FirebaseService();
    $key = $service->push('events', ['data' => 'value']);

    expect($key)->toBe('');
});

// ---------------------------------------------------------------------------
// Test 10 — remove() sends DELETE request to the correct path
// ---------------------------------------------------------------------------

test('remove() sends DELETE to the correct Firebase Database URL', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/sessions/abc.json' => Http::response(null, 200),
    ]);

    $service = new FirebaseService();
    $result = $service->remove('sessions/abc');

    expect($result)->toBeTrue();

    Http::assertSent(function ($req) {
        return $req->method() === 'DELETE'
            && str_contains($req->url(), '/sessions/abc.json');
    });
});

// ---------------------------------------------------------------------------
// Test 11 — remove() returns false on DELETE failure
// ---------------------------------------------------------------------------

test('remove() returns false when Firebase DELETE fails', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/*' => Http::response('Forbidden', 403),
    ]);

    $service = new FirebaseService();
    $result = $service->remove('protected/node');

    expect($result)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Test 12 — uploadFile() sends POST to the Firebase Storage REST endpoint
// ---------------------------------------------------------------------------

test('uploadFile() posts to the Google Cloud Storage REST API', function () {
    config([
        'firebase.project_id'   => 'my-project',
        'firebase.database_url' => 'https://my-project.firebaseio.com',
    ]);

    Http::fake([
        'https://storage.googleapis.com/upload/storage/v1/b/*' => Http::response(
            ['name' => 'docs/report.pdf', 'bucket' => 'my-bucket'],
            200
        ),
    ]);

    $service = new FirebaseService();
    $result = $service->uploadFile('my-bucket', 'docs/report.pdf', '%PDF-1.4', 'application/pdf');

    expect($result['error'])->toBeNull()
        ->and($result['name'])->toBe('docs/report.pdf')
        ->and($result['bucket'])->toBe('my-bucket');

    Http::assertSent(function ($req) {
        return str_contains($req->url(), 'storage.googleapis.com/upload/storage/v1/b/my-bucket/o');
    });
});

// ---------------------------------------------------------------------------
// Test 13 — uploadFile() returns error when Storage rejects the upload
// ---------------------------------------------------------------------------

test('uploadFile() returns error structure when Firebase Storage upload fails', function () {
    config([
        'firebase.project_id'   => 'my-project',
        'firebase.database_url' => 'https://my-project.firebaseio.com',
    ]);

    Http::fake([
        'https://storage.googleapis.com/*' => Http::response('Storage Error', 500),
    ]);

    $service = new FirebaseService();
    $result = $service->uploadFile('bucket', 'file.txt', 'contents');

    expect($result['name'])->toBeNull()
        ->and($result['bucket'])->toBeNull()
        ->and($result['error'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 14 — set() returns error structure on PUT failure
// ---------------------------------------------------------------------------

test('set() returns error structure when Firebase Database PUT fails', function () {
    config([
        'firebase.project_id'   => 'erp-test',
        'firebase.database_url' => 'https://erp-test.firebaseio.com',
    ]);

    Http::fake([
        'https://erp-test.firebaseio.com/*' => Http::response('Permission denied', 403),
    ]);

    $service = new FirebaseService();
    $result = $service->set('restricted/path', ['value' => 1]);

    expect($result['data'])->toBeNull()
        ->and($result['error'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 15 — All methods use Http::fake() — no real Firebase calls are made
// ---------------------------------------------------------------------------

test('all Firebase methods degrade gracefully and make no real HTTP calls', function () {
    config([
        'firebase.project_id'   => 'test-project',
        'firebase.database_url' => 'https://test-project.firebaseio.com',
        'firebase.credentials'  => '',
    ]);

    Http::fake([
        '*' => Http::response('Service Unavailable', 503),
    ]);

    $service = new FirebaseService();

    $set    = $service->set('path/node', ['k' => 'v']);
    $get    = $service->get('path/node');
    $push   = $service->push('path/list', ['k' => 'v']);
    $remove = $service->remove('path/node');
    $push_n = $service->sendPushNotification('token', 'T', 'B');
    $upload = $service->uploadFile('bkt', 'f.txt', 'data');

    expect($set['data'])->toBeNull()
        ->and($get['data'])->toBeNull()
        ->and($push)->toBe('')
        ->and($remove)->toBeFalse()
        ->and($push_n)->toBeFalse()
        ->and($upload['name'])->toBeNull();
});
