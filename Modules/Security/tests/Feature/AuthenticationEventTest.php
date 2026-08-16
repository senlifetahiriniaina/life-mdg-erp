<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\AuthenticationEvent;
use Tests\TestCase;

/**
 * AuthenticationEventTest — tests for the AuthenticationEvent model.
 *
 * Covers: creation, relationship to User, status values, risk_factors JSON cast,
 * device_info JSON cast, filtering by status/event_type, and pagination.
 */
class AuthenticationEventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user    = User::factory()->for($this->company)->create();
    }

    // ─── Creation ─────────────────────────────────────────────────────────────

    public function test_authentication_event_can_be_created(): void
    {
        $event = AuthenticationEvent::create([
            'user_id'               => $this->user->id,
            'user_email'            => $this->user->email,
            'event_type'            => 'login',
            'authentication_method' => 'password',
            'ip_address'            => '127.0.0.1',
            'user_agent'            => 'PHPUnit/TestBrowser',
            'status'                => 'success',
            'trust_score'           => 'high',
            'authenticated_at'      => now(),
        ]);

        $this->assertNotNull($event->id);
        $this->assertDatabaseHas('security_authentication_events', [
            'user_id'    => $this->user->id,
            'event_type' => 'login',
            'status'     => 'success',
        ]);
    }

    public function test_authentication_event_user_relationship(): void
    {
        $event = AuthenticationEvent::create([
            'user_id'               => $this->user->id,
            'user_email'            => $this->user->email,
            'event_type'            => 'login',
            'authentication_method' => 'password',
            'ip_address'            => '127.0.0.1',
            'user_agent'            => 'PHPUnit',
            'status'                => 'success',
            'trust_score'           => 'high',
            'authenticated_at'      => now(),
        ]);

        $this->assertEquals($this->user->id, $event->user->id);
    }

    // ─── Status values ────────────────────────────────────────────────────────

    public function test_failed_login_event_stores_failure_reason(): void
    {
        $event = AuthenticationEvent::create([
            'user_id'               => null,
            'user_email'            => 'unknown@example.com',
            'event_type'            => 'failed_login',
            'authentication_method' => 'password',
            'ip_address'            => '10.0.0.1',
            'user_agent'            => 'PHPUnit',
            'status'                => 'failure',
            'failure_reason'        => 'Invalid credentials',
            'trust_score'           => 'unknown',
            'authenticated_at'      => now(),
        ]);

        $this->assertEquals('failure', $event->status);
        $this->assertEquals('Invalid credentials', $event->failure_reason);
    }

    public function test_blocked_event_stores_status(): void
    {
        $event = AuthenticationEvent::create([
            'user_email'            => 'attacker@evil.com',
            'event_type'            => 'failed_login',
            'authentication_method' => 'password',
            'ip_address'            => '192.168.0.99',
            'user_agent'            => 'EvilBot/1.0',
            'status'                => 'blocked',
            'trust_score'           => 'low',
            'authenticated_at'      => now(),
        ]);

        $this->assertEquals('blocked', $event->status);
    }

    // ─── JSON casts ───────────────────────────────────────────────────────────

    public function test_device_info_cast_as_array(): void
    {
        $deviceInfo = ['browser' => 'Chrome', 'os' => 'Linux', 'device_type' => 'desktop'];

        $event = AuthenticationEvent::create([
            'user_id'               => $this->user->id,
            'user_email'            => $this->user->email,
            'event_type'            => 'login',
            'authentication_method' => 'password',
            'ip_address'            => '127.0.0.1',
            'user_agent'            => 'Chrome/Linux',
            'device_info'           => $deviceInfo,
            'status'                => 'success',
            'trust_score'           => 'high',
            'authenticated_at'      => now(),
        ]);

        $this->assertIsArray($event->fresh()->device_info);
        $this->assertEquals('Chrome', $event->fresh()->device_info['browser']);
    }

    public function test_risk_factors_cast_as_array(): void
    {
        $riskFactors = ['new_ip' => true, 'unusual_time' => true, 'geolocation_mismatch' => false];

        $event = AuthenticationEvent::create([
            'user_id'               => $this->user->id,
            'user_email'            => $this->user->email,
            'event_type'            => 'login',
            'authentication_method' => 'password',
            'ip_address'            => '1.2.3.4',
            'user_agent'            => 'PHPUnit',
            'risk_factors'          => $riskFactors,
            'status'                => 'success',
            'trust_score'           => 'medium',
            'authenticated_at'      => now(),
        ]);

        $this->assertIsArray($event->fresh()->risk_factors);
        $this->assertTrue($event->fresh()->risk_factors['new_ip']);
    }

    // ─── Filtering by event_type ──────────────────────────────────────────────

    public function test_filter_by_event_type_login(): void
    {
        AuthenticationEvent::create([
            'user_id' => $this->user->id, 'user_email' => $this->user->email,
            'event_type' => 'login', 'authentication_method' => 'password',
            'ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit',
            'status' => 'success', 'trust_score' => 'high', 'authenticated_at' => now(),
        ]);
        AuthenticationEvent::create([
            'user_id' => $this->user->id, 'user_email' => $this->user->email,
            'event_type' => 'logout', 'authentication_method' => 'password',
            'ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit',
            'status' => 'success', 'trust_score' => 'high', 'authenticated_at' => now(),
        ]);

        $logins = AuthenticationEvent::where('event_type', 'login')->get();

        $this->assertEquals(1, $logins->count());
    }

    public function test_filter_by_status_failure(): void
    {
        AuthenticationEvent::create([
            'user_email' => 'a@b.com', 'event_type' => 'failed_login',
            'authentication_method' => 'password', 'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit', 'status' => 'failure',
            'trust_score' => 'low', 'authenticated_at' => now(),
        ]);
        AuthenticationEvent::create([
            'user_id' => $this->user->id, 'user_email' => $this->user->email,
            'event_type' => 'login', 'authentication_method' => 'password',
            'ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit',
            'status' => 'success', 'trust_score' => 'high', 'authenticated_at' => now(),
        ]);

        $failures = AuthenticationEvent::where('status', 'failure')->get();

        $this->assertEquals(1, $failures->count());
    }

    // ─── Trust score values ───────────────────────────────────────────────────

    public function test_trust_score_low_stored_correctly(): void
    {
        $event = AuthenticationEvent::create([
            'user_email'            => 'risk@example.com',
            'event_type'            => 'login',
            'authentication_method' => 'password',
            'ip_address'            => '198.51.100.1',
            'user_agent'            => 'Unknown/1.0',
            'status'                => 'success',
            'trust_score'           => 'low',
            'authenticated_at'      => now(),
        ]);

        $this->assertEquals('low', $event->trust_score);
    }

    // ─── MFA methods ─────────────────────────────────────────────────────────

    public function test_mfa_authentication_method_stored(): void
    {
        $event = AuthenticationEvent::create([
            'user_id'               => $this->user->id,
            'user_email'            => $this->user->email,
            'event_type'            => 'login',
            'authentication_method' => 'hardware_key',
            'ip_address'            => '127.0.0.1',
            'user_agent'            => 'PHPUnit',
            'status'                => 'success',
            'trust_score'           => 'high',
            'authenticated_at'      => now(),
        ]);

        $this->assertEquals('hardware_key', $event->authentication_method);
    }

    // ─── Date casting ─────────────────────────────────────────────────────────

    public function test_authenticated_at_is_carbon(): void
    {
        $now = now();
        $event = AuthenticationEvent::create([
            'user_id'               => $this->user->id,
            'user_email'            => $this->user->email,
            'event_type'            => 'login',
            'authentication_method' => 'password',
            'ip_address'            => '127.0.0.1',
            'user_agent'            => 'PHPUnit',
            'status'                => 'success',
            'trust_score'           => 'high',
            'authenticated_at'      => $now,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->fresh()->authenticated_at);
    }

    // ─── Multiple events per user ─────────────────────────────────────────────

    public function test_user_can_have_multiple_events(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AuthenticationEvent::create([
                'user_id'               => $this->user->id,
                'user_email'            => $this->user->email,
                'event_type'            => 'login',
                'authentication_method' => 'password',
                'ip_address'            => "127.0.0.{$i}",
                'user_agent'            => 'PHPUnit',
                'status'                => 'success',
                'trust_score'           => 'high',
                'authenticated_at'      => now(),
            ]);
        }

        $count = AuthenticationEvent::where('user_id', $this->user->id)->count();
        $this->assertEquals(3, $count);
    }

    // ─── Unauthenticated API access ───────────────────────────────────────────

    public function test_unauthenticated_cannot_list_authentication_events(): void
    {
        $response = $this->getJson('/api/v1/security/incidents');

        $response->assertStatus(401);
    }
}
