<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\SessionEnhanced;
use Tests\TestCase;

/**
 * Chantier 38.1: activates Modules\Core\Services\SessionManagementDashboard —
 * fully real and tested at the service layer (device fingerprinting, hijack
 * detection, concurrent-session limits) but confirmed via grep to have zero
 * controller/route consumer anywhere in the app before this. New
 * SessionManagementController exposes a "my sessions" self-service surface:
 * list own sessions, view one's detail/timeline, terminate one, terminate all
 * others.
 *
 * The `terminate-others returns 422` test below locks in an empirically
 * confirmed, honestly-documented limitation rather than glossing over it:
 * SessionEnhanced rows are only ever written by the `session.security`
 * middleware (App\Http\Middleware\SanctumSessionSecurity) when
 * $user->currentAccessToken() is a REAL Laravel\Sanctum\PersonalAccessToken —
 * and confirmed via a real tinker session (Auth::guard('web')->login() then
 * Auth::guard('sanctum')->user()->currentAccessToken()) that Sanctum's own
 * stateful-SPA guard resolution — what every real Inertia/session-cookie
 * login in this app actually goes through — produces a TransientToken
 * instead, every time. actingAs($user, 'sanctum') in this test suite produces
 * the identical TransientToken, so this test exercises the exact real-world
 * shape rather than a synthetic best case.
 */
class Chantier381SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_callers_own_active_sessions()
    {
        $me = $this->actingAsUser('admin');
        $other = \App\Models\User::factory()->create();

        SessionEnhanced::factory()->create(['user_id' => $me->id]);
        SessionEnhanced::factory()->create(['user_id' => $me->id]);
        SessionEnhanced::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/v1/sessions');

        $response->assertOk();
        expect($response->json('sessions'))->toHaveCount(2);
        expect($response->json('summary.total_active_sessions'))->toBe(2);
    }

    public function test_expired_sessions_are_excluded_from_the_list()
    {
        $me = $this->actingAsUser('admin');
        SessionEnhanced::factory()->create(['user_id' => $me->id]);
        SessionEnhanced::factory()->create(['user_id' => $me->id, 'expires_at' => now()->subHour()]);

        $response = $this->getJson('/api/v1/sessions');

        $response->assertOk();
        expect($response->json('sessions'))->toHaveCount(1);
    }

    public function test_can_view_detail_of_own_session()
    {
        $me = $this->actingAsUser('admin');
        $session = SessionEnhanced::factory()->create(['user_id' => $me->id]);

        $response = $this->getJson("/api/v1/sessions/{$session->id}");

        $response->assertOk();
        expect($response->json('session.id'))->toBe($session->id);
    }

    public function test_cannot_view_detail_of_another_users_session()
    {
        $this->actingAsUser('admin');
        $other = \App\Models\User::factory()->create();
        $session = SessionEnhanced::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson("/api/v1/sessions/{$session->id}");

        $response->assertNotFound();
    }

    public function test_returns_404_for_a_session_that_does_not_exist()
    {
        $this->actingAsUser('admin');

        $response = $this->getJson('/api/v1/sessions/does-not-exist');

        $response->assertNotFound();
    }

    public function test_can_view_timeline_of_own_session_but_not_another_users()
    {
        $me = $this->actingAsUser('admin');
        $other = \App\Models\User::factory()->create();
        $mine = SessionEnhanced::factory()->create(['user_id' => $me->id]);
        $theirs = SessionEnhanced::factory()->create(['user_id' => $other->id]);

        $this->getJson("/api/v1/sessions/{$mine->id}/timeline")->assertOk();
        $this->getJson("/api/v1/sessions/{$theirs->id}/timeline")->assertNotFound();
    }

    public function test_can_terminate_own_session()
    {
        $me = $this->actingAsUser('admin');
        $session = SessionEnhanced::factory()->create(['user_id' => $me->id]);

        $response = $this->deleteJson("/api/v1/sessions/{$session->id}");

        $response->assertOk();
        expect($response->json('success'))->toBeTrue();
        $this->assertDatabaseMissing('sessions_enhanced', ['id' => $session->id]);
    }

    public function test_cannot_terminate_another_users_session()
    {
        $this->actingAsUser('admin');
        $other = \App\Models\User::factory()->create();
        $session = SessionEnhanced::factory()->create(['user_id' => $other->id]);

        $response = $this->deleteJson("/api/v1/sessions/{$session->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('sessions_enhanced', ['id' => $session->id]);
    }

    public function test_terminate_others_reports_the_untracked_transient_token_case_honestly()
    {
        // actingAsUser() authenticates via actingAs($user, 'sanctum') — the
        // exact TransientToken shape confirmed empirically to match real
        // Inertia logins in this app. There is no real "current session id"
        // to preserve, so the endpoint must say so explicitly (422) rather
        // than silently terminating everything or guessing.
        $me = $this->actingAsUser('admin');
        SessionEnhanced::factory()->create(['user_id' => $me->id]);

        $response = $this->postJson('/api/v1/sessions/terminate-others');

        $response->assertStatus(422);
        expect($response->json('success'))->toBeFalse();
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $this->getJson('/api/v1/sessions')->assertUnauthorized();
    }
}
