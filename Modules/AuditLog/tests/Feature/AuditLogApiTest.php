<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function auditApiUser(): User
{
    $perm = Permission::firstOrCreate(['name' => 'auditlog.logs.view-any', 'guard_name' => 'sanctum']);
    $user = User::factory()->create();
    $user->givePermissionTo($perm);
    return $user;
}

function seedLogs(User $user, int $count = 5, string $module = 'CRM', string $eventType = 'model_created'): void
{
    for ($i = 0; $i < $count; $i++) {
        AuditLog::create([
            'action'     => 'created',
            'module'     => $module,
            'event_type' => $eventType,
            'user_id'    => $user->id,
            'user_name'  => $user->name,
            'description'=> "Entry {$i}",
        ]);
    }
}

// ─── index ────────────────────────────────────────────────────────────────────

test('GET /api/v1/audit/logs returns paginated audit logs', function () {
    $user = auditApiUser();
    seedLogs($user, 3);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
});

test('GET /api/v1/audit/logs without auth returns 401', function () {
    $this->getJson('/api/v1/audit/logs')
        ->assertUnauthorized();
});

test('GET /api/v1/audit/logs filters by module', function () {
    $user = auditApiUser();
    seedLogs($user, 2, 'CRM');
    seedLogs($user, 3, 'HR');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs?module=CRM')
        ->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    foreach ($data as $row) {
        expect($row['module'])->toBe('CRM');
    }
});

test('GET /api/v1/audit/logs filters by event_type', function () {
    $user = auditApiUser();
    seedLogs($user, 2, 'CRM', 'model_created');
    seedLogs($user, 3, 'HR',  'login');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs?event_type=login')
        ->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    foreach ($data as $row) {
        expect($row['event_type'])->toBe('login');
    }
});

test('GET /api/v1/audit/logs filters by user_id', function () {
    $user1 = auditApiUser();
    $user2 = User::factory()->create();

    seedLogs($user1, 2, 'CRM');
    seedLogs($user2, 3, 'HR');

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson("/api/v1/audit/logs?user_id={$user1->id}")
        ->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    foreach ($data as $row) {
        expect($row['user_id'])->toBe($user1->id);
    }
});

test('GET /api/v1/audit/logs filters by date_from', function () {
    $user = auditApiUser();

    // Create an old log manually with past date
    AuditLog::create([
        'action'     => 'login',
        'module'     => 'Auth',
        'event_type' => 'login',
        'user_id'    => $user->id,
        'user_name'  => $user->name,
        'created_at' => now()->subDays(10),
    ]);

    seedLogs($user, 2); // These are created "now"

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs?date_from=' . now()->subDay()->toDateString())
        ->assertOk();

    $data = $response->json('data');
    // Only the recent logs should appear — none older than 1 day
    foreach ($data as $row) {
        expect(strtotime($row['created_at']))->toBeGreaterThan(strtotime(now()->subDays(2)->toDateTimeString()));
    }
});

// ─── show ─────────────────────────────────────────────────────────────────────

test('GET /api/v1/audit/logs/{id} returns a single audit log entry', function () {
    $user = auditApiUser();
    $log  = AuditLog::create([
        'action'     => 'created',
        'module'     => 'Inventory',
        'event_type' => 'model_created',
        'user_id'    => $user->id,
        'user_name'  => $user->name,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/audit/logs/{$log->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $log->id, 'module' => 'Inventory']);
});

test('GET /api/v1/audit/logs/{id} returns 404 for missing entry', function () {
    $user = auditApiUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs/999999')
        ->assertNotFound()
        ->assertJsonFragment(['message' => 'Audit log entry not found']);
});

// ─── stats ────────────────────────────────────────────────────────────────────

test('GET /api/v1/audit/logs/stats returns statistics structure', function () {
    $user = auditApiUser();
    seedLogs($user, 3, 'CRM', 'model_created');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs/stats')
        ->assertOk()
        ->assertJsonStructure(['today', 'this_week', 'this_month', 'by_module', 'by_event_type']);
});

test('GET /api/v1/audit/logs/stats today count reflects entries created today', function () {
    $user = auditApiUser();
    seedLogs($user, 4);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs/stats')
        ->assertOk();

    expect($response->json('today'))->toBeGreaterThanOrEqual(4);
});

// ─── export ───────────────────────────────────────────────────────────────────

test('GET /api/v1/audit/logs/export returns all matching entries as JSON', function () {
    $user = auditApiUser();
    seedLogs($user, 5, 'HR');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs/export')
        ->assertOk()
        ->assertJsonStructure(['count', 'data']);

    expect($response->json('count'))->toBeGreaterThanOrEqual(5);
});

test('GET /api/v1/audit/logs/export respects module filter', function () {
    $user = auditApiUser();
    seedLogs($user, 3, 'Sales');
    seedLogs($user, 2, 'HR');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit/logs/export?module=Sales')
        ->assertOk();

    $data = $response->json('data');
    foreach ($data as $row) {
        expect($row['module'])->toBe('Sales');
    }
    expect(count($data))->toBe(3);
});
