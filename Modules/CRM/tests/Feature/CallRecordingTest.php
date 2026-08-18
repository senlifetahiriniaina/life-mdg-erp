<?php

declare(strict_types=1);

namespace Modules\CRM\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\CRM\Jobs\SummarizeCallJob;
use Modules\CRM\Models\CallLog;
use Modules\CRM\Models\CallRecording;
use Modules\CRM\Services\VoipService;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeCallLog(array $overrides = []): CallLog
{
    return CallLog::create(array_merge([
        'user_id'          => User::factory()->create()->id,
        'direction'        => 'outbound',
        'status'           => 'answered',
        'phone_number'     => '+221700000000',
        'called_at'        => now(),
        'company_id'       => 1,
    ], $overrides));
}

function makeAiMock(array $guidance = []): AiContextualAssistantService
{
    $mock = \Mockery::mock(AiContextualAssistantService::class);
    $mock->shouldReceive('getGuidance')
        ->andReturn(array_merge([
            'enabled'              => true,
            'what_to_do'           => 'Follow up on pricing discussion.',
            'how_to_do'            => ['Send a quote by tomorrow.'],
            'decision_indicators'  => [],
            'warnings'             => [],
            'next_actions'         => ['Send quote'],
            'tips'                 => ['positive'],
        ], $guidance));

    return $mock;
}

// ---------------------------------------------------------------------------
// Tests: startRecording
// ---------------------------------------------------------------------------

test('can_start_recording_creates_record', function () {
    Http::fake();

    $callLog  = makeCallLog();
    $service  = new VoipService();
    $recording = $service->startRecording($callLog->id);

    expect($recording)->toBeInstanceOf(CallRecording::class);
    expect(CallRecording::where('call_id', $callLog->id)->count())->toBe(1);
});

test('start_recording_status_is_recording', function () {
    Http::fake();

    $callLog  = makeCallLog();
    $service   = new VoipService();
    $recording = $service->startRecording($callLog->id);

    expect($recording->status)->toBe(CallRecording::STATUS_RECORDING);
});

// ---------------------------------------------------------------------------
// Tests: stopRecording
// ---------------------------------------------------------------------------

test('can_stop_recording_updates_status', function () {
    Http::fake();

    $callLog = makeCallLog();

    CallRecording::create([
        'call_id'    => $callLog->id,
        'status'     => CallRecording::STATUS_RECORDING,
        'company_id' => 1,
    ]);

    $service   = new VoipService();
    $recording = $service->stopRecording($callLog->id);

    expect($recording)->toBeInstanceOf(CallRecording::class);
});

test('stop_recording_sets_status_processing', function () {
    Http::fake();

    $callLog = makeCallLog();

    CallRecording::create([
        'call_id'    => $callLog->id,
        'status'     => CallRecording::STATUS_RECORDING,
        'company_id' => 1,
    ]);

    $service   = new VoipService();
    $recording = $service->stopRecording($callLog->id);

    expect($recording->status)->toBe(CallRecording::STATUS_PROCESSING);
});

// ---------------------------------------------------------------------------
// Tests: generateAiSummary
// ---------------------------------------------------------------------------

test('generate_ai_summary_returns_array', function () {
    $callLog = makeCallLog();

    CallRecording::create([
        'call_id'         => $callLog->id,
        'status'          => CallRecording::STATUS_PROCESSING,
        'transcript_text' => 'Client interested in the premium plan.',
        'company_id'      => 1,
    ]);

    app()->instance(AiContextualAssistantService::class, makeAiMock());

    $service = new VoipService();
    $result  = $service->generateAiSummary($callLog->id);

    expect($result)->toBeArray();
});

test('ai_summary_has_required_keys', function () {
    $callLog = makeCallLog();

    CallRecording::create([
        'call_id'         => $callLog->id,
        'status'          => CallRecording::STATUS_PROCESSING,
        'transcript_text' => 'Discussed pricing and delivery.',
        'company_id'      => 1,
    ]);

    app()->instance(AiContextualAssistantService::class, makeAiMock());

    $service = new VoipService();
    $result  = $service->generateAiSummary($callLog->id);

    expect($result)->toHaveKeys(['summary', 'action_items', 'sentiment', 'next_step_suggestion']);
});

test('fallback_when_ai_unavailable_returns_static', function () {
    $callLog = makeCallLog();

    CallRecording::create([
        'call_id'         => $callLog->id,
        'status'          => CallRecording::STATUS_PROCESSING,
        'transcript_text' => 'Some transcript.',
        'company_id'      => 1,
    ]);

    // AI service throws — should gracefully fall back
    $failingMock = \Mockery::mock(AiContextualAssistantService::class);
    $failingMock->shouldReceive('getGuidance')->andThrow(new \RuntimeException('Service unavailable'));
    app()->instance(AiContextualAssistantService::class, $failingMock);

    $service = new VoipService();
    $result  = $service->generateAiSummary($callLog->id);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['summary', 'action_items', 'sentiment', 'next_step_suggestion', 'enabled']);
    expect($result['enabled'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// Tests: saveCallLog
// ---------------------------------------------------------------------------

test('save_call_log_updates_outcome', function () {
    $callLog = makeCallLog(['status' => 'initiated']);

    $service = new VoipService();
    $updated = $service->saveCallLog($callLog->id, 'answered', 'Client confirmed order.');

    expect($updated->status)->toBe('answered');
    expect($updated->notes)->toBe('Client confirmed order.');
});

// ---------------------------------------------------------------------------
// Tests: SummarizeCallJob dispatching
// ---------------------------------------------------------------------------

test('summarize_endpoint_dispatches_job', function () {
    Queue::fake();

    // Chantier 10 fix: CallRecordingController::summarize() now authorizes via
    // CallRecordingPolicy against crm.call-recording.summarize + a company_id tenant match —
    // this permission string is new in this chantier and not yet in RolesAndPermissionsSeeder
    // (a documented Phase B follow-up), so it's created directly here rather than via the
    // central seeder.
    Permission::firstOrCreate(['name' => 'crm.call-recording.summarize', 'guard_name' => 'web']);

    // users.company_id is a real foreign key onto companies — a bare literal id would violate
    // the FK constraint under RefreshDatabase's fresh schema.
    $companyId = \App\Models\Company::factory()->create()->id;

    $user = User::factory()->create(['company_id' => $companyId]);
    $user->givePermissionTo('crm.call-recording.summarize');
    $callLog = makeCallLog(['user_id' => $user->id]);

    CallRecording::create([
        'call_id'    => $callLog->id,
        'status'     => CallRecording::STATUS_PROCESSING,
        'company_id' => $companyId,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/crm/calls/{$callLog->id}/summarize")
        ->assertStatus(202);

    Queue::assertPushed(SummarizeCallJob::class, function ($job) use ($callLog) {
        return $job->callId === $callLog->id;
    });
});
