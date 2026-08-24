<?php

declare(strict_types=1);

use App\Models\User;
use Modules\CRM\Models\CallLog;
use Modules\CRM\Services\VoipService;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list call logs', function () {
    CallLog::factory()->count(3)->create(['user_id' => $this->user->id]);

    $this->withToken($this->token)
        ->getJson('/api/v1/crm/voip/call-logs')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page']);
});

it('can initiate a call', function () {
    $this->mock(VoipService::class, function ($mock) {
        $mock->shouldReceive('initiateCall')
            ->once()
            ->andReturn(['call_sid' => 'CA-test-123', 'status' => 'initiated']);
    });

    $this->withToken($this->token)
        ->postJson('/api/v1/crm/voip/call', [
            'phone' => '+15551234567',
        ])
        ->assertStatus(201)
        ->assertJsonPath('call_sid', 'CA-test-123');
});

it('returns 401 for unauthenticated call log requests', function () {
    $this->getJson('/api/v1/crm/voip/call-logs')
        ->assertUnauthorized();
});

/**
 * Chantier 38.3: crm_call_logs never had a call_sid column at all — VoipService::
 * initiateCall() fetched a real Twilio SID but silently threw it away, which permanently
 * disabled startRecording()'s real Twilio "start recording" API call (its
 * `$callLog->call_sid ?? null` guard could never be true) and left the real, already-existing
 * ClickToCallButton.vue's status poll with no route and nothing to look a call up by.
 */
it('persists the real Twilio call_sid returned by initiateCall, via the real endpoint', function () {
    \Illuminate\Support\Facades\Http::fake([
        'api.twilio.com/*' => \Illuminate\Support\Facades\Http::response([
            'sid' => 'CA-real-sid-123',
            'status' => 'queued',
        ], 201),
    ]);

    $this->withToken($this->token)
        ->postJson('/api/v1/crm/voip/call', ['phone' => '+15551234567'])
        ->assertStatus(201)
        ->assertJsonPath('call_sid', 'CA-real-sid-123');

    $this->assertDatabaseHas('crm_call_logs', [
        'call_sid' => 'CA-real-sid-123',
        'phone_number' => '+15551234567',
        'status' => 'initiated',
    ]);
});

it('GET voip/status returns the real Twilio status for a call the caller owns', function () {
    $log = CallLog::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->user->company_id,
        'call_sid' => 'CA-status-test',
    ]);

    $this->mock(VoipService::class, function ($mock) {
        $mock->shouldReceive('getCallStatus')->once()->with('CA-status-test')->andReturn('in-progress');
    });

    $this->withToken($this->token)
        ->getJson('/api/v1/crm/voip/status?call_sid=CA-status-test')
        ->assertOk()
        ->assertJson(['status' => 'in-progress']);
});

it('GET voip/status 404s for a call_sid belonging to another company', function () {
    $company = \App\Models\Company::create(['name' => 'Other Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $other = User::factory()->create(['company_id' => $company->id]);
    CallLog::factory()->create(['user_id' => $other->id, 'tenant_id' => $company->id, 'call_sid' => 'CA-other-co']);

    $this->withToken($this->token)
        ->getJson('/api/v1/crm/voip/status?call_sid=CA-other-co')
        ->assertNotFound();
});

it('GET voip/status 422s when call_sid is missing', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/crm/voip/status')
        ->assertStatus(422);
});

it('the Twilio webhook matches on the real call_sid rather than the old phone+status heuristic', function () {
    $log = CallLog::factory()->create([
        'user_id' => $this->user->id,
        'call_sid' => 'CA-webhook-test',
        'status' => 'initiated',
        'phone_number' => '+15550001111',
    ]);
    // A second, unrelated call to the same number, still 'initiated' — the old heuristic
    // (phone_number + status='initiated', latest()) could have matched either row
    // ambiguously; matching by call_sid is deterministic.
    CallLog::factory()->create([
        'user_id' => $this->user->id,
        'call_sid' => 'CA-other-call',
        'status' => 'initiated',
        'phone_number' => '+15550001111',
    ]);

    // Chantier 38.3: this route is still behind auth:sanctum (a real, documented-but-not-
    // fixed bug — see VoipController::webhook()'s own docblock: the real Twilio server has
    // no Sanctum token and would get a 401 here in production) — authenticating is what the
    // real code path requires today, not what a fixed version should require.
    $this->withToken($this->token)
        ->postJson('/api/v1/crm/voip/webhook', [
            'CallSid' => 'CA-webhook-test',
            'CallStatus' => 'in-progress',
            'To' => '+15550001111',
        ])->assertOk();

    expect($log->fresh()->status)->toBe('answered');
    expect(CallLog::where('call_sid', 'CA-other-call')->first()->status)->toBe('initiated');
});
