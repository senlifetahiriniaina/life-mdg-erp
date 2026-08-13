<?php

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;
use Modules\Core\Services\MobileAuthService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MobileAuthServiceTest extends TestCase
{
    private MobileAuthService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MobileAuthService::class);
        $this->user = User::factory()->create([
            'email' => 'mobile@test.com',
            'password' => bcrypt('password123'),
        ]);
    }

    /** @test */
    public function it_authenticates_user_with_email_and_password()
    {
        $response = $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');

        $this->assertNotNull($response);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('refresh_token', $response);
        $this->assertArrayHasKey('expires_in', $response);
    }

    /** @test */
    public function it_fails_with_invalid_credentials()
    {
        $response = $this->service->login('mobile@test.com', 'wrongpassword', 'iPhone', 'ios');

        $this->assertNull($response);
    }

    /** @test */
    public function it_registers_device_during_login()
    {
        $response = $this->service->login('mobile@test.com', 'password123', 'Device123', 'android');

        $device = DB::table('mobile_devices')
            ->where('user_id', $this->user->id)
            ->where('platform', 'android')
            ->first();

        $this->assertNotNull($device);
        $this->assertEquals('Device123', $device->device_name);
    }

    /** @test */
    public function it_handles_biometric_enrollment()
    {
        $response = $this->service->registerBiometric(
            $this->user,
            'fingerprint',
            'biometric_template_data'
        );

        $this->assertTrue($response);

        $biometric = DB::table('mobile_biometrics')->first();
        $this->assertNotNull($biometric);
        $this->assertEquals('fingerprint', $biometric->type);
    }

    /** @test */
    public function it_authenticates_with_biometric()
    {
        // Register device
        $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');

        // Enroll biometric
        $this->service->registerBiometric($this->user, 'face', 'face_template_data');

        // Authenticate with biometric
        $response = $this->service->loginBiometric($this->user->id, 'face', 'face_template_data');

        $this->assertNotNull($response);
        $this->assertArrayHasKey('token', $response);
    }

    /** @test */
    public function it_refreshes_expired_token()
    {
        $loginResponse = $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');
        $refreshToken = $loginResponse['refresh_token'];

        $newResponse = $this->service->refreshToken($refreshToken);

        $this->assertNotNull($newResponse);
        $this->assertArrayHasKey('token', $newResponse);
        $this->assertNotEquals($loginResponse['token'], $newResponse['token']);
    }

    /** @test */
    public function it_logs_device_for_tracking()
    {
        $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');

        $logs = DB::table('mobile_login_logs')->where('user_id', $this->user->id)->get();

        $this->assertGreaterThan(0, $logs->count());
        $this->assertEquals('success', $logs->first()->status);
    }

    /** @test */
    public function it_detects_suspicious_activity_rapid_ip_change()
    {
        $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');

        // Simulate rapid IP change
        DB::table('mobile_login_logs')->updateOrCreate(
            ['user_id' => $this->user->id],
            ['ip_address' => '192.168.1.1']
        );

        $isSuspicious = $this->service->detectSuspiciousActivity(
            $this->user->id,
            '10.0.0.1', // Different IP
            'device123'
        );

        $this->assertTrue($isSuspicious);
    }

    /** @test */
    public function it_revokes_specific_device()
    {
        $loginResponse = $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');
        $token = $loginResponse['token'];

        $result = $this->service->revokeDevice($this->user, $token);

        $this->assertTrue($result);

        $session = DB::table('mobile_sessions')
            ->where('token', $token)
            ->first();

        $this->assertTrue($session->is_revoked);
    }

    /** @test */
    public function it_logs_out_all_devices()
    {
        $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');
        $this->service->login('mobile@test.com', 'password123', 'Android', 'android');

        $result = $this->service->logoutAllDevices($this->user);

        $this->assertTrue($result);

        $activeSessions = DB::table('mobile_sessions')
            ->where('user_id', $this->user->id)
            ->where('is_revoked', false)
            ->count();

        $this->assertEquals(0, $activeSessions);
    }

    /** @test */
    public function it_retrieves_user_devices()
    {
        $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');
        $this->service->login('mobile@test.com', 'password123', 'Android', 'android');

        $devices = $this->service->getUserDevices($this->user);

        $this->assertIsArray($devices);
        $this->assertGreaterThanOrEqual(2, count($devices));
    }

    /** @test */
    public function it_gets_login_history()
    {
        $this->service->login('mobile@test.com', 'password123', 'iPhone', 'ios');
        $this->service->login('mobile@test.com', 'password123', 'Android', 'android');

        $history = $this->service->getLoginHistory($this->user, limit: 10);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(2, count($history));
    }

    /** @test */
    public function it_tracks_failed_login_attempts()
    {
        $this->service->login('mobile@test.com', 'wrongpassword', 'iPhone', 'ios');
        $this->service->login('mobile@test.com', 'wrongpassword', 'iPhone', 'ios');
        $this->service->login('mobile@test.com', 'wrongpassword', 'iPhone', 'ios');

        $failedAttempts = DB::table('mobile_login_logs')
            ->where('user_id', $this->user->id)
            ->where('status', 'failed')
            ->count();

        $this->assertGreaterThanOrEqual(3, $failedAttempts);
    }
}
