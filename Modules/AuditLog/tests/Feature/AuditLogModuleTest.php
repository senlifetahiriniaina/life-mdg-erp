<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AuditLog\Providers\AuditLogServiceProvider;
use Modules\Core\Models\AuditLog;
use Modules\Core\Services\AuditService;

uses(RefreshDatabase::class);

// ─── Service Provider ─────────────────────────────────────────────────────────

test('AuditLog service provider can be resolved from container', function () {
    expect(app()->bound(AuditLogServiceProvider::class) || class_exists(AuditLogServiceProvider::class))
        ->toBeTrue();
});

// ─── AuditService — logging actions ──────────────────────────────────────────

test('AuditService logs a create action', function () {
    $service = app(AuditService::class);
    $user    = User::factory()->create();

    $log = $service->log(
        action:      'create',
        userId:      $user->id,
        module:      'AuditLog',
        eventType:   'create',
        description: 'Created resource',
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->action)->toBe('create')
        ->and($log->module)->toBe('AuditLog')
        ->and($log->user_id)->toBe($user->id);
});

test('AuditService logs an update action with old and new values', function () {
    $service = app(AuditService::class);

    $log = $service->log(
        action:    'update',
        module:    'Inventory',
        oldValues: ['stock' => 100],
        newValues: ['stock' => 80],
    );

    expect($log->action)->toBe('update')
        ->and($log->old_values)->toBe(['stock' => 100])
        ->and($log->new_values)->toBe(['stock' => 80]);
});

test('AuditService logs a delete action', function () {
    $service = app(AuditService::class);

    $log = $service->log(
        action:    'delete',
        module:    'CRM',
        eventType: 'delete',
    );

    expect($log->action)->toBe('delete')
        ->and($log->module)->toBe('CRM');
});

test('AuditService logLogin creates login entry', function () {
    $service = app(AuditService::class);
    $user    = User::factory()->create();

    $log = $service->logLogin($user->id);

    expect($log->action)->toBe('login')
        ->and($log->module)->toBe('Auth')
        ->and($log->event_type)->toBe('login')
        ->and($log->user_id)->toBe($user->id);
});

test('AuditService logLogout creates logout entry', function () {
    $service = app(AuditService::class);
    $user    = User::factory()->create();

    $log = $service->logLogout($user->id);

    expect($log->action)->toBe('logout')
        ->and($log->event_type)->toBe('logout');
});

// ─── AuditLog model ───────────────────────────────────────────────────────────

test('AuditLog model uses correct table name', function () {
    expect((new AuditLog())->getTable())->toBe('core_audit_logs');
});

test('AuditLog model can be created via factory', function () {
    $log = AuditLog::factory()->create();

    expect($log->id)->not->toBeNull()
        ->and($log->action)->not->toBeEmpty();
});

test('AuditLog factory forCreate state sets correct action', function () {
    $log = AuditLog::factory()->forCreate()->create();

    expect($log->action)->toBe('created')
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toBeArray();
});

test('AuditLog factory forUpdate state has old and new values', function () {
    $log = AuditLog::factory()->forUpdate()->create();

    expect($log->action)->toBe('updated')
        ->and($log->old_values)->toBeArray()
        ->and($log->new_values)->toBeArray();
});

test('AuditLog factory forDelete state clears new_values', function () {
    $log = AuditLog::factory()->forDelete()->create();

    expect($log->action)->toBe('deleted')
        ->and($log->new_values)->toBeNull();
});

// ─── Filtering ────────────────────────────────────────────────────────────────

test('AuditLog can be queried by action', function () {
    AuditLog::factory()->count(3)->create(['action' => 'created']);
    AuditLog::factory()->count(2)->create(['action' => 'updated']);

    expect(AuditLog::where('action', 'created')->count())->toBe(3)
        ->and(AuditLog::where('action', 'updated')->count())->toBe(2);
});

test('AuditLog can be filtered by user_id', function () {
    $user = User::factory()->create();

    AuditLog::factory()->count(4)->create(['user_id' => $user->id]);
    AuditLog::factory()->count(2)->create(['user_id' => null]);

    expect(AuditLog::where('user_id', $user->id)->count())->toBe(4);
});

test('AuditLog can be filtered by module', function () {
    AuditLog::factory()->count(5)->create(['module' => 'HR']);
    AuditLog::factory()->count(3)->create(['module' => 'CRM']);

    expect(AuditLog::where('module', 'HR')->count())->toBe(5);
});

test('AuditLog records ip_address from factory', function () {
    $log = AuditLog::factory()->create(['ip_address' => '192.168.1.1']);

    expect($log->ip_address)->toBe('192.168.1.1');
});
