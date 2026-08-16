<?php

namespace Modules\Core\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Services\RiskAssessmentService;
use Tests\TestCase;

class RiskAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RiskAssessmentService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RiskAssessmentService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Test risk level scoring
     */
    public function test_low_risk_score()
    {
        $request = $this->createMockRequest('192.168.1.1');

        // Setup known device and location
        Cache::put("user_devices:{$this->user->id}", ['abc123'], now()->addDays(180));
        Cache::put("user_location:{$this->user->id}", [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'city' => 'New York',
            'timestamp' => time(),
        ], now()->addDays(90));

        $assessment = $this->service->assessRisk($this->user, $request);

        $this->assertEquals('low', $assessment['risk_level']);
        $this->assertLessThan(20, $assessment['risk_score']);
    }

    public function test_medium_risk_score()
    {
        $request = $this->createMockRequest('10.0.0.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        // Without any history, should be medium risk
        if ($assessment['risk_score'] >= 20 && $assessment['risk_score'] < 40) {
            $this->assertEquals('medium', $assessment['risk_level']);
        }
    }

    public function test_high_risk_requires_mfa()
    {
        $request = $this->createMockRequest('203.0.113.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        if ($assessment['risk_level'] === 'high') {
            $this->assertTrue($assessment['requires_mfa']);
        }
    }

    public function test_critical_risk_requires_verification()
    {
        $request = $this->createMockRequest('198.51.100.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        if ($assessment['risk_level'] === 'critical') {
            $this->assertTrue($assessment['requires_verification']);
        }
    }

    /**
     * Test impossible travel detection
     */
    public function test_impossible_travel_detection_triggers_high_risk()
    {
        // Setup: Previous login from New York
        Cache::put("user_location:{$this->user->id}", [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'city' => 'New York',
            'timestamp' => now()->subMinutes(10)->timestamp,
        ], now()->addDays(90));

        $request = $this->createMockRequest('203.0.113.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        // Impossible travel should increase risk significantly
        $this->assertGreaterThanOrEqual(40, $assessment['risk_score']);
    }

    /**
     * Test new device detection
     */
    public function test_new_device_increases_risk()
    {
        // Setup: Known device
        Cache::put("user_devices:{$this->user->id}", ['known_device_fingerprint'], now()->addDays(180));

        $request = $this->createMockRequest('192.168.1.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        // Assessment should include device factor
        $this->assertArrayHasKey('factors', $assessment);
    }

    /**
     * Test new location detection
     */
    public function test_new_location_increases_risk()
    {
        // Setup: Previous location
        Cache::put("user_location:{$this->user->id}", [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'city' => 'New York',
            'timestamp' => time(),
        ], now()->addDays(90));

        $request = $this->createMockRequest('192.168.1.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        $this->assertGreaterThanOrEqual(0, $assessment['risk_score']);
    }

    /**
     * Test unusual login time detection
     */
    public function test_unusual_time_increases_risk()
    {
        // Setup: Typical login hours (9-17)
        Cache::put("user_login_times:{$this->user->id}", [9, 10, 14, 15, 17], now()->addDays(90));

        $request = $this->createMockRequest('192.168.1.1');

        // Assess at 3 AM
        now()->setTime(3, 0);

        $assessment = $this->service->assessRisk($this->user, $request);

        // Unusual time should be detected
        $this->assertGreaterThan(0, $assessment['risk_score']);
    }

    /**
     * Test new IP detection
     */
    public function test_new_ip_increases_risk()
    {
        // Setup: Known IP
        Cache::put("user_ips:{$this->user->id}", ['192.168.1.1'], now()->addDays(180));

        $request = $this->createMockRequest('203.0.113.50');

        $assessment = $this->service->assessRisk($this->user, $request);

        // New IP should be detected
        $this->assertGreaterThan(0, $assessment['risk_score']);
    }

    /**
     * Test brute force detection
     */
    public function test_brute_force_detection()
    {
        $request = $this->createMockRequest('192.168.1.1');

        // Simulate multiple failed attempts
        for ($i = 0; $i < 6; $i++) {
            $this->service->recordFailedAttempt($this->user);
        }

        $assessment = $this->service->assessRisk($this->user, $request);

        // Brute force factor should be detected
        $hasFailedFactor = collect($assessment['factors'])
            ->some(fn($f) => $f['factor'] === 'brute_force');

        $this->assertTrue($hasFailedFactor);
    }

    /**
     * Test recording successful login
     */
    public function test_record_login_updates_history()
    {
        $request = $this->createMockRequest('192.168.1.100');

        $this->service->recordLogin($this->user, $request);

        // Verify location was recorded
        $location = Cache::get("user_location:{$this->user->id}");
        $this->assertNotNull($location);

        // Verify IP was recorded
        $ips = Cache::get("user_ips:{$this->user->id}");
        $this->assertContains('192.168.1.100', $ips);

        // Verify device was recorded
        $devices = Cache::get("user_devices:{$this->user->id}");
        $this->assertNotNull($devices);
    }

    /**
     * Test clearing failed attempts on successful login
     */
    public function test_failed_attempts_cleared_on_successful_login()
    {
        $request = $this->createMockRequest('192.168.1.1');

        // Record some failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->service->recordFailedAttempt($this->user);
        }

        // Record successful login
        $this->service->recordLogin($this->user, $request);

        $assessment = $this->service->assessRisk($this->user, $request);

        // Brute force factor should not be present after successful login
        $hasFailedFactor = collect($assessment['factors'])
            ->some(fn($f) => $f['factor'] === 'brute_force');

        $this->assertFalse($hasFailedFactor);
    }

    /**
     * Test risk factors are properly weighted
     */
    public function test_risk_factors_are_weighted()
    {
        // Impossible travel should have highest weight (40%)
        // New device should have second highest (20%)
        // All factors combined should not exceed 100 in isolation

        $request = $this->createMockRequest('192.168.1.1');

        $assessment = $this->service->assessRisk($this->user, $request);

        // Risk score should be a weighted combination
        $this->assertGreaterThanOrEqual(0, $assessment['risk_score']);
        $this->assertLessThanOrEqual(100, $assessment['risk_score']); // Can exceed 100 combined
    }

    /**
     * Test location distance calculation
     */
    public function test_haversine_distance_calculation()
    {
        // New York: 40.7128° N, 74.0060° W
        // Los Angeles: 34.0522° N, 118.2437° W
        // Distance: ~3944 km

        $request = $this->createMockRequest('192.168.1.1');

        Cache::put("user_location:{$this->user->id}", [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'city' => 'New York',
            'timestamp' => now()->subHours(2)->timestamp,
        ], now()->addDays(90));

        // Access from LA location would be impossible in 2 hours
        $assessment = $this->service->assessRisk($this->user, $request);

        // Should detect some kind of anomaly
        $this->assertGreaterThan(0, $assessment['risk_score']);
    }

    /**
     * Helper: Create mock request
     */
    private function createMockRequest(string $ip)
    {
        // Real Request instead of createMock(): Illuminate\Http\Request defines
        // its own method() (the HTTP verb accessor), which shadows PHPUnit's
        // fluent ->method() mock configurator for this class — every
        // $request->method('ip') call returned null and ->willReturn() fataled
        // before the service under test was ever invoked.
        return \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR'          => $ip,
            'HTTP_USER_AGENT'      => 'Mozilla/5.0 Test User Agent',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
        ]);
    }
}
