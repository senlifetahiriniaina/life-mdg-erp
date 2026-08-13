<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Timesheets\Services\TimerService;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── TimerService unit tests ───────────────────────────────────────────────────

test('start returns timer state with timer_id and started_at', function () {
    $service = app(TimerService::class);
    $state   = $service->start(userId: 1, context: ['description' => 'Design review']);

    expect($state)->toHaveKeys(['timer_id', 'started_at', 'description'])
        ->and($state['description'])->toBe('Design review');
});

test('start stores timer state in cache', function () {
    $service = app(TimerService::class);
    $service->start(userId: 2);

    expect($service->hasActiveTimer(2))->toBeTrue();
});

test('start throws if timer already running for user', function () {
    $service = app(TimerService::class);
    $service->start(userId: 3);

    expect(fn () => $service->start(userId: 3))
        ->toThrow(\RuntimeException::class, 'already running');
});

test('stop returns duration_minutes and clears cache', function () {
    $service = app(TimerService::class);
    $service->start(userId: 4);

    $result = $service->stop(userId: 4);

    expect($result)->toHaveKeys(['timer_id', 'duration_seconds', 'duration_minutes', 'stopped_at'])
        ->and($service->hasActiveTimer(4))->toBeFalse();
});

test('stop throws if no timer is running', function () {
    $service = app(TimerService::class);

    expect(fn () => $service->stop(userId: 99))
        ->toThrow(\RuntimeException::class, 'No active timer');
});

test('getActiveTimer returns null when no timer running', function () {
    $service = app(TimerService::class);
    expect($service->getActiveTimer(100))->toBeNull();
});

test('getActiveTimer returns state after start', function () {
    $service = app(TimerService::class);
    $service->start(userId: 5, context: ['project_id' => 42]);

    $state = $service->getActiveTimer(5);
    expect($state)->not->toBeNull()
        ->and($state['project_id'])->toBe(42);
});

test('clear removes active timer without persisting entry', function () {
    $service = app(TimerService::class);
    $service->start(userId: 6);
    $service->clear(userId: 6);

    expect($service->hasActiveTimer(6))->toBeFalse();
});

test('duration is computed correctly', function () {
    $service = app(TimerService::class);

    // Manually inject a state with known start time
    Cache::put('timesheet_timer:7', [
        'timer_id'    => 'test_timer',
        'user_id'     => 7,
        'started_at'  => now()->subMinutes(30)->toIso8601String(),
        'project_id'  => null,
        'task_id'     => null,
        'description' => 'Manual test',
    ], now()->addHour());

    $result = $service->stop(userId: 7);

    expect($result['duration_minutes'])->toBeGreaterThanOrEqual(30)
        ->and($result['duration_seconds'])->toBeGreaterThanOrEqual(1800);
});

// ── API endpoint tests ────────────────────────────────────────────────────────

test('POST /api/v1/timesheets/timer/start requires auth', function () {
    $this->postJson('/api/v1/timesheets/timer/start')
        ->assertUnauthorized();
});

test('POST /api/v1/timesheets/timer/start starts a timer for authenticated user', function () {
    actingAsUser('employee');

    $this->postJson('/api/v1/timesheets/timer/start', [
        'description' => 'Feature development',
    ])->assertStatus(201)
      ->assertJsonStructure(['timer_id', 'started_at']);
});

test('POST /api/v1/timesheets/timer/stop stops a running timer', function () {
    actingAsUser('employee');

    $this->postJson('/api/v1/timesheets/timer/start', ['description' => 'Work session']);
    $this->postJson('/api/v1/timesheets/timer/stop')
        ->assertOk()
        ->assertJsonStructure(['timer_id', 'duration_minutes', 'stopped_at']);
});

test('GET /api/v1/timesheets/timer/current returns not running when no timer active', function () {
    actingAsUser('employee');

    $this->getJson('/api/v1/timesheets/timer/current')
        ->assertOk()
        ->assertJsonPath('running', false);
});

test('GET /api/v1/timesheets/timer/current returns running state', function () {
    actingAsUser('employee');

    $this->postJson('/api/v1/timesheets/timer/start');
    $this->getJson('/api/v1/timesheets/timer/current')
        ->assertOk()
        ->assertJsonPath('running', true)
        ->assertJsonStructure(['elapsed_seconds', 'timer']);
});

test('DELETE /api/v1/timesheets/timer/discard clears the timer', function () {
    actingAsUser('employee');

    $this->postJson('/api/v1/timesheets/timer/start');
    $this->deleteJson('/api/v1/timesheets/timer/discard')
        ->assertOk()
        ->assertJsonPath('message', 'Timer discarded.');

    $this->getJson('/api/v1/timesheets/timer/current')
        ->assertJsonPath('running', false);
});
