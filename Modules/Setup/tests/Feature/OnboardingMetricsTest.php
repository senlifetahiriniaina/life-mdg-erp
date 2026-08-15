<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Setup\Models\FunnelSnapshot;
use Modules\Setup\Models\OnboardingSession;
use Modules\Setup\Models\OnboardingStepEvent;
use Modules\Setup\Services\OnboardingMetricsService;

uses(RefreshDatabase::class);

beforeEach(function () {
    // setup_onboarding_sessions.user_id / setup_onboarding_step_events.user_id
    // are real FKs to users — several tests below reference hardcoded ids
    // (1, 42) directly, so pre-create matching users rather than rewrite
    // every assertion.
    \App\Models\User::factory()->create(['id' => 1]);
    \App\Models\User::factory()->create(['id' => 42]);
});

// ============================================================
// Helpers
// ============================================================

function makeOnboardingUser(int $companyId = 1): \App\Models\User
{
    return \App\Models\User::factory()->create(['company_id' => $companyId]);
}

function makeSession(array $overrides = []): OnboardingSession
{
    return OnboardingSession::create(array_merge([
        'tenant_id'    => 1,
        'user_id'      => 1,
        'source_type'  => 'file_csv',
        'started_at'   => Carbon::now()->subMinutes(3),
        'current_step' => 1,
    ], $overrides));
}

// ============================================================
// 1. startSession creates a record with correct fields
// ============================================================
it('startSession creates an OnboardingSession with correct fields', function () {
    $service = app(OnboardingMetricsService::class);

    $session = $service->startSession(tenantId: 1, userId: 42, sourceType: 'file_excel');

    expect($session)->toBeInstanceOf(OnboardingSession::class);
    expect($session->tenant_id)->toBe(1);
    expect($session->user_id)->toBe(42);
    expect($session->source_type)->toBe('file_excel');
    expect($session->current_step)->toBe(1);
    expect($session->completed_at)->toBeNull();
    expect($session->abandoned_at)->toBeNull();

    $this->assertDatabaseHas('setup_onboarding_sessions', [
        'tenant_id'   => 1,
        'user_id'     => 42,
        'source_type' => 'file_excel',
    ]);
});

// ============================================================
// 2. recordStep creates OnboardingStepEvent records
// ============================================================
it('recordStep creates an OnboardingStepEvent record', function () {
    $service = app(OnboardingMetricsService::class);
    $session = makeSession();

    $service->recordStep($session, step: 1, event: 'started', durationSeconds: 0);

    expect(OnboardingStepEvent::where('onboarding_session_id', $session->id)->count())->toBe(1);

    $event = OnboardingStepEvent::where('onboarding_session_id', $session->id)->first();
    expect($event->step)->toBe(1);
    expect($event->event)->toBe('started');
    expect($event->tenant_id)->toBe(1);
    expect($event->user_id)->toBe(1);
});

// ============================================================
// 3. recordStep advances current_step when event = 'completed'
// ============================================================
it('recordStep advances current_step on the session when step is completed', function () {
    $service = app(OnboardingMetricsService::class);
    $session = makeSession(['current_step' => 1]);

    $service->recordStep($session, step: 1, event: 'completed', durationSeconds: 45);

    $session->refresh();
    expect($session->current_step)->toBe(2);
});

// ============================================================
// 4. recordStep stores duration_seconds and metadata
// ============================================================
it('recordStep stores duration_seconds and metadata correctly', function () {
    $service  = app(OnboardingMetricsService::class);
    $session  = makeSession();
    $metadata = ['file_size_kb' => 128, 'columns_detected' => 8];

    $service->recordStep($session, step: 2, event: 'completed', durationSeconds: 120, metadata: $metadata);

    $event = OnboardingStepEvent::where('onboarding_session_id', $session->id)->first();
    expect($event->duration_seconds)->toBe(120);
    expect($event->metadata)->toBe($metadata);
});

// ============================================================
// 5. completeSession sets completed_at and calculates duration
// ============================================================
it('completeSession sets completed_at and calculates total_duration_seconds', function () {
    $service = app(OnboardingMetricsService::class);
    $session = makeSession(['started_at' => Carbon::now()->subSeconds(200)]);

    $service->completeSession($session, rowsImported: 50, aiUsed: true, aiAcceptedPercent: 80.0);

    $session->refresh();
    expect($session->completed_at)->not->toBeNull();
    expect($session->total_duration_seconds)->toBeGreaterThanOrEqual(199);
    expect($session->rows_imported)->toBe(50);
    expect($session->ai_mapping_used)->toBeTrue();
    expect((float) $session->ai_mapping_accepted_percent)->toBe(80.0);
});

// ============================================================
// 6. abandonSession sets abandoned_at and current_step
// ============================================================
it('abandonSession sets abandoned_at and records the step at abandonment', function () {
    $service = app(OnboardingMetricsService::class);
    $session = makeSession(['current_step' => 2]);

    $service->abandonSession($session, atStep: 2);

    $session->refresh();
    expect($session->abandoned_at)->not->toBeNull();
    expect($session->current_step)->toBe(2);
    expect($session->completed_at)->toBeNull();
});

// ============================================================
// 7. isCompletedUnder5Min returns true when duration <= 300s
// ============================================================
it('isCompletedUnder5Min returns true when total_duration_seconds <= 300', function () {
    $session = makeSession(['total_duration_seconds' => 299]);
    expect($session->isCompletedUnder5Min())->toBeTrue();

    $session300 = makeSession(['total_duration_seconds' => 300]);
    expect($session300->isCompletedUnder5Min())->toBeTrue();
});

// ============================================================
// 8. isCompletedUnder5Min returns false when duration > 300s
// ============================================================
it('isCompletedUnder5Min returns false when total_duration_seconds > 300', function () {
    $session = makeSession(['total_duration_seconds' => 301]);
    expect($session->isCompletedUnder5Min())->toBeFalse();
});

// ============================================================
// 9. isCompletedUnder5Min returns false when duration is null
// ============================================================
it('isCompletedUnder5Min returns false when total_duration_seconds is null', function () {
    $session = makeSession(['total_duration_seconds' => null]);
    expect($session->isCompletedUnder5Min())->toBeFalse();
});

// ============================================================
// 10. getDurationMinutes converts seconds to minutes correctly
// ============================================================
it('getDurationMinutes returns correct float value', function () {
    $session = makeSession(['total_duration_seconds' => 180]); // 3 minutes exactly
    expect($session->getDurationMinutes())->toBe(3.0);

    $session2 = makeSession(['total_duration_seconds' => 150]); // 2.5 minutes
    expect($session2->getDurationMinutes())->toBe(2.5);

    $session3 = makeSession(['total_duration_seconds' => null]);
    expect($session3->getDurationMinutes())->toBeNull();
});

// ============================================================
// 11. getStats returns correct completion_rate
// ============================================================
it('getStats calculates completion_rate correctly', function () {
    $service = app(OnboardingMetricsService::class);

    // 2 completed, 1 abandoned, 1 still active — total 4
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 240]);
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 180]);
    makeSession(['tenant_id' => 1, 'abandoned_at' => Carbon::now()]);
    makeSession(['tenant_id' => 1]); // still in progress

    $stats = $service->getStats(tenantId: 1, days: 30);

    expect($stats['total_sessions'])->toBe(4);
    expect($stats['sessions_completed'])->toBe(2);
    expect($stats['completion_rate'])->toBe(50.0);
});

// ============================================================
// 12. getStats returns correct avg_duration_min
// ============================================================
it('getStats calculates avg_duration_min correctly', function () {
    $service = app(OnboardingMetricsService::class);

    // 2 completed sessions: 120s + 180s = avg 150s = 2.5 min
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 120]);
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 180]);

    $stats = $service->getStats(tenantId: 1, days: 30);

    expect($stats['avg_duration_min'])->toBe(2.5);
});

// ============================================================
// 13. getStats returns correct median_duration_min
// ============================================================
it('getStats calculates median_duration_min correctly', function () {
    $service = app(OnboardingMetricsService::class);

    // 3 sessions: 60s, 120s, 300s → median = 120s = 2.0 min
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 60]);
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 120]);
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 300]);

    $stats = $service->getStats(tenantId: 1, days: 30);

    expect($stats['median_duration_min'])->toBe(2.0);
});

// ============================================================
// 14. getStats returns empty stats shape when no sessions exist
// ============================================================
it('getStats returns zero values when there are no sessions', function () {
    $service = app(OnboardingMetricsService::class);

    $stats = $service->getStats(tenantId: 99, days: 30);

    expect($stats['total_sessions'])->toBe(0);
    expect($stats['completion_rate'])->toBe(0.0);
    expect($stats['avg_duration_min'])->toBeNull();
    expect($stats['under_5_min_rate'])->toBe(0.0);
    expect($stats['step_funnel'])->toHaveCount(5);
    expect($stats['top_source_types'])->toBeEmpty();
});

// ============================================================
// 15. step_funnel rates are calculated correctly
// ============================================================
it('getStats computes step_funnel completion rates', function () {
    $service = app(OnboardingMetricsService::class);

    // 4 sessions: tenant 1
    $s1 = makeSession(['tenant_id' => 1]);
    $s2 = makeSession(['tenant_id' => 1]);
    $s3 = makeSession(['tenant_id' => 1]);
    $s4 = makeSession(['tenant_id' => 1]);

    // All 4 complete step 1; 3 complete step 2; 2 complete step 3
    foreach ([$s1, $s2, $s3, $s4] as $s) {
        OnboardingStepEvent::create(['tenant_id' => 1, 'user_id' => 1, 'onboarding_session_id' => $s->id, 'step' => 1, 'event' => 'completed']);
    }
    foreach ([$s1, $s2, $s3] as $s) {
        OnboardingStepEvent::create(['tenant_id' => 1, 'user_id' => 1, 'onboarding_session_id' => $s->id, 'step' => 2, 'event' => 'completed']);
    }
    foreach ([$s1, $s2] as $s) {
        OnboardingStepEvent::create(['tenant_id' => 1, 'user_id' => 1, 'onboarding_session_id' => $s->id, 'step' => 3, 'event' => 'completed']);
    }

    $stats  = $service->getStats(tenantId: 1, days: 30);
    $funnel = $stats['step_funnel'];

    // Step 1: 4/4 = 100%, Step 2: 3/4 = 75%, Step 3: 2/4 = 50%
    $byStep = array_column($funnel, 'completion_rate', 'step');
    expect($byStep[1])->toBe(100.0);
    expect($byStep[2])->toBe(75.0);
    expect($byStep[3])->toBe(50.0);
    expect($byStep[4])->toBe(0.0);
    expect($byStep[5])->toBe(0.0);
});

// ============================================================
// 16. AI adoption rate is tracked correctly
// ============================================================
it('getStats calculates ai_adoption_rate correctly', function () {
    $service = app(OnboardingMetricsService::class);

    makeSession(['tenant_id' => 1, 'ai_mapping_used' => true]);
    makeSession(['tenant_id' => 1, 'ai_mapping_used' => true]);
    makeSession(['tenant_id' => 1, 'ai_mapping_used' => false]);
    makeSession(['tenant_id' => 1, 'ai_mapping_used' => false]);

    $stats = $service->getStats(tenantId: 1, days: 30);

    expect($stats['ai_adoption_rate'])->toBe(50.0);
});

// ============================================================
// 17. under_5_min_rate is tracked correctly
// ============================================================
it('getStats calculates under_5_min_rate correctly', function () {
    $service = app(OnboardingMetricsService::class);

    // 3 completed: 2 under 5 min, 1 over
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 200]);
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 295]);
    makeSession(['tenant_id' => 1, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 400]);

    $stats = $service->getStats(tenantId: 1, days: 30);

    // 2 out of 3 completed = 66.67%
    expect($stats['under_5_min_rate'])->toBe(66.67);
});

// ============================================================
// 18. Tenant isolation — getStats only returns data for the correct tenant
// ============================================================
it('getStats enforces tenant isolation', function () {
    $service = app(OnboardingMetricsService::class);

    // Tenant 1 has 3 sessions; tenant 2 has 1 session
    makeSession(['tenant_id' => 1]);
    makeSession(['tenant_id' => 1]);
    makeSession(['tenant_id' => 1]);
    makeSession(['tenant_id' => 2]);

    $statsT1 = $service->getStats(tenantId: 1, days: 30);
    $statsT2 = $service->getStats(tenantId: 2, days: 30);

    expect($statsT1['total_sessions'])->toBe(3);
    expect($statsT2['total_sessions'])->toBe(1);
});

// ============================================================
// 19. generateDailySnapshot creates correct funnel snapshot
// ============================================================
it('generateDailySnapshot persists a FunnelSnapshot for the given date', function () {
    $service = app(OnboardingMetricsService::class);
    $date    = Carbon::today();

    // 3 sessions today: 2 completed, 1 abandoned
    makeSession(['tenant_id' => 1, 'started_at' => $date->copy()->addHour(), 'completed_at' => $date->copy()->addHours(2), 'total_duration_seconds' => 120, 'ai_mapping_used' => true]);
    makeSession(['tenant_id' => 1, 'started_at' => $date->copy()->addHours(3), 'completed_at' => $date->copy()->addHours(4), 'total_duration_seconds' => 240]);
    makeSession(['tenant_id' => 1, 'started_at' => $date->copy()->addHours(5), 'abandoned_at' => $date->copy()->addHours(6)]);

    $snapshot = $service->generateDailySnapshot(tenantId: 1, date: $date);

    expect($snapshot)->toBeInstanceOf(FunnelSnapshot::class);
    expect($snapshot->sessions_started)->toBe(3);
    expect($snapshot->sessions_completed)->toBe(2);
    expect($snapshot->sessions_abandoned)->toBe(1);
    expect($snapshot->avg_duration_seconds)->toBe(180); // (120 + 240) / 2
    // 1 of 3 uses AI → 33.33%
    expect((float) $snapshot->ai_mapping_adoption_rate)->toBe(33.33);

    $this->assertDatabaseHas('setup_onboarding_funnel_snapshots', [
        'tenant_id'     => 1,
        'snapshot_date' => $date->toDateString(),
    ]);
});

// ============================================================
// 20. API endpoints are protected by auth:sanctum
// ============================================================
it('returns 401 for unauthenticated requests to onboarding endpoints', function () {
    $this->postJson('/api/v1/setup/onboarding/start')->assertStatus(401);
    $this->getJson('/api/v1/setup/onboarding/stats')->assertStatus(401);
    $this->getJson('/api/v1/setup/onboarding/stats/export')->assertStatus(401);
    $this->postJson('/api/v1/setup/onboarding/1/step')->assertStatus(401);
    $this->postJson('/api/v1/setup/onboarding/1/complete')->assertStatus(401);
    $this->postJson('/api/v1/setup/onboarding/1/abandon')->assertStatus(401);
});

// ============================================================
// 21. POST /start creates a session via the API
// ============================================================
it('POST /onboarding/start returns 201 and creates a session', function () {
    $user = makeOnboardingUser();

    $response = $this->actingAs($user)->postJson('/api/v1/setup/onboarding/start', [
        'source_type' => 'file_csv',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.source_type', 'file_csv')
        ->assertJsonPath('data.current_step', 1);

    $this->assertDatabaseHas('setup_onboarding_sessions', [
        'tenant_id'   => $user->company_id,
        'user_id'     => $user->id,
        'source_type' => 'file_csv',
    ]);
});

// ============================================================
// 22. POST /start validates source_type enum
// ============================================================
it('POST /onboarding/start returns 422 for invalid source_type', function () {
    $user = makeOnboardingUser();

    $response = $this->actingAs($user)->postJson('/api/v1/setup/onboarding/start', [
        'source_type' => 'unknown_type',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['source_type']]);
});

// ============================================================
// 23. POST /{id}/complete marks session and returns under_5_min flag
// ============================================================
it('POST /onboarding/{id}/complete marks session completed and returns KPI flags', function () {
    $user    = makeOnboardingUser();
    $session = makeSession(['tenant_id' => $user->company_id, 'user_id' => $user->id, 'started_at' => Carbon::now()->subSeconds(250)]);

    $response = $this->actingAs($user)->postJson("/api/v1/setup/onboarding/{$session->id}/complete", [
        'rows_imported'               => 100,
        'ai_mapping_used'             => true,
        'ai_mapping_accepted_percent' => 90.0,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('under_5_min', true);

    $session->refresh();
    expect($session->completed_at)->not->toBeNull();
    expect($session->rows_imported)->toBe(100);
});

// ============================================================
// 24. POST /{id}/abandon marks session as abandoned
// ============================================================
it('POST /onboarding/{id}/abandon sets abandoned_at on the session', function () {
    $user    = makeOnboardingUser();
    $session = makeSession(['tenant_id' => $user->company_id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/api/v1/setup/onboarding/{$session->id}/abandon", [
        'at_step' => 3,
    ]);

    $response->assertStatus(200);

    $session->refresh();
    expect($session->abandoned_at)->not->toBeNull();
    expect($session->current_step)->toBe(3);
});

// ============================================================
// 25. GET /stats returns correct shape for the tenant
// ============================================================
it('GET /onboarding/stats returns a valid funnel stats payload', function () {
    $user = makeOnboardingUser();
    makeSession(['tenant_id' => $user->company_id, 'completed_at' => Carbon::now(), 'total_duration_seconds' => 200]);

    $response = $this->actingAs($user)->getJson('/api/v1/setup/onboarding/stats?days=30');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_sessions',
                'sessions_completed',
                'sessions_abandoned',
                'completion_rate',
                'avg_duration_min',
                'median_duration_min',
                'under_5_min_rate',
                'step_funnel',
                'ai_adoption_rate',
                'top_source_types',
            ],
            'period' => ['days'],
        ]);
});
