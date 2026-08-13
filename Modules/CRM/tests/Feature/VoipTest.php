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
