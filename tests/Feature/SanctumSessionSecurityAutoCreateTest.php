<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\SessionEnhanced;
use Tests\TestCase;

/**
 * A Sanctum token issued before SessionSecurityService::createSession() was
 * wired into the login flow (or before session.security was applied to a
 * given route group) has no matching sessions_enhanced row. Before this
 * fix, SanctumSessionSecurity::handle() treated that as "Session not
 * found" -> 419, which meant applying the middleware to any new route
 * group would hard-reject every already-logged-in session on its very
 * first request there. This is the actual regression guard for the fix
 * described in the plan as "session pré-existante sur une route
 * nouvellement couverte ne 419 plus".
 */
class SanctumSessionSecurityAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_with_no_session_record_is_auto_created_instead_of_419(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api');
        $user->withAccessToken($token->accessToken);
        $this->actingAs($user, 'sanctum');

        $this->assertNull(SessionEnhanced::find((string) $token->accessToken->id));

        $response = $this->getJson('/api/v1/hr/departments');

        $response->assertStatus(200);
        $this->assertNotNull(SessionEnhanced::find((string) $token->accessToken->id));
    }

    public function test_a_genuinely_hijacked_session_still_gets_a_419(): void
    {
        $user = User::factory()->create();
        $someoneElseId = $user->id + 999999;
        $token = $user->createToken('api');
        $user->withAccessToken($token->accessToken);
        $this->actingAs($user, 'sanctum');

        // A session record already exists for this token, but bound to a
        // different user_id — validateSession() must still catch this as a
        // hijack attempt; only a genuinely *missing* record gets auto-created.
        SessionEnhanced::create([
            'id' => (string) $token->accessToken->id,
            'user_id' => $someoneElseId,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHour(),
            'regeneration_count' => 0,
            'concurrent_session_number' => 1,
            'suspicious_activity_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/hr/departments');

        $response->assertStatus(419);
    }
}
