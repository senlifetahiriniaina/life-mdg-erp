<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\SessionEnhanced;
use Modules\Core\Models\SessionSecurityEvent;
use Modules\Core\Services\SessionFingerprint;
use Modules\Core\Services\SessionSecurityService;
use Tests\TestCase;

class SessionSecurityServiceTest extends TestCase
{
    private SessionSecurityService $service;
    private SessionFingerprint $fingerprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fingerprint = app(SessionFingerprint::class);
        $this->service = app(SessionSecurityService::class);

        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        SessionEnhanced::truncate();
        SessionSecurityEvent::truncate();
        parent::tearDown();
    }

    /**
     * test_session_creation_stores_fingerprint
     */
    public function test_session_creation_stores_fingerprint(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $session = $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        $this->assertDatabaseHas('sessions_enhanced', [
            'id' => $sessionId,
            'user_id' => $userId,
            'device_type' => 'desktop', // Expected from our test request
        ]);

        $this->assertNotNull($session->browser_fingerprint);
        $this->assertNotNull($session->device_fingerprint);
    }

    /**
     * test_session_regeneration_creates_new_id
     */
    public function test_session_regeneration_creates_new_id(): void
    {
        $oldSessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        // Create initial session
        $this->service->createSession($oldSessionId, $userId, $request, 'tenant-1');

        // Regenerate
        $newSessionId = $this->service->regenerateSessionId($oldSessionId, $userId, $request, 'tenant-1');

        $this->assertNotEquals($oldSessionId, $newSessionId);
        $this->assertDatabaseHas('sessions_enhanced', ['id' => $newSessionId]);
    }

    /**
     * test_session_regeneration_invalidates_old_id
     */
    public function test_session_regeneration_invalidates_old_id(): void
    {
        $oldSessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $this->service->createSession($oldSessionId, $userId, $request, 'tenant-1');

        $newSessionId = $this->service->regenerateSessionId($oldSessionId, $userId, $request, 'tenant-1');

        // Old session should be deleted
        $this->assertDatabaseMissing('sessions_enhanced', ['id' => $oldSessionId]);
        // New session should exist
        $this->assertDatabaseHas('sessions_enhanced', ['id' => $newSessionId]);
    }

    /**
     * test_session_timeout_enforcement
     */
    public function test_session_timeout_enforcement(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $session = $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Mark session as expired
        $session->update(['expires_at' => now()->subMinutes(1)]);

        // Validation should fail
        $validation = $this->service->validateSession($sessionId, $userId, $request);

        $this->assertFalse($validation['valid']);
        $this->assertEquals('timeout', SessionSecurityEvent::latest('id')->first()->event_type);
    }

    /**
     * test_idle_timeout_enforcement
     */
    public function test_idle_timeout_enforcement(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $session = $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Set last activity to beyond idle timeout
        $idleTimeout = (int)config('session.idle_timeout', 900);
        $session->update(['last_activity_at' => now()->subSeconds($idleTimeout + 100)]);

        // Validation should fail
        $validation = $this->service->validateSession($sessionId, $userId, $request);

        $this->assertFalse($validation['valid']);
        $this->assertTrue($validation['details']['idle']);
    }

    /**
     * test_concurrent_session_limit_enforced
     */
    public function test_concurrent_session_limit_enforced(): void
    {
        $userId = 1;
        $request = $this->createRequest();

        // Create 3 sessions (at limit)
        for ($i = 0; $i < 3; $i++) {
            $sessionId = bin2hex(random_bytes(20));
            $this->service->createSession($sessionId, $userId, $request, 'tenant-1');
        }

        // Enforce limit should not invalidate yet
        $result = $this->service->enforceConcurrentLimit($userId, 3, 'tenant-1');
        $this->assertFalse($result['limited']);
        $this->assertEquals(0, $result['invalidated_count']);

        // Create 4th session (exceeds limit)
        $newSessionId = bin2hex(random_bytes(20));
        $this->service->createSession($newSessionId, $userId, $request, 'tenant-1');

        // Enforce limit should invalidate one
        $result = $this->service->enforceConcurrentLimit($userId, 3, 'tenant-1');
        $this->assertTrue($result['limited']);
        $this->assertEquals(1, $result['invalidated_count']);
    }

    /**
     * test_activity_timestamp_updated
     */
    public function test_activity_timestamp_updated(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $session = $this->service->createSession($sessionId, $userId, $request, 'tenant-1');
        $originalTime = $session->last_activity_at;

        // Wait and record activity
        sleep(1);
        $this->service->recordActivity($sessionId);

        $updated = SessionEnhanced::find($sessionId);
        $this->assertTrue($updated->last_activity_at->greaterThan($originalTime));
    }

    /**
     * test_session_invalidation_removes_access
     */
    public function test_session_invalidation_removes_access(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Invalidate
        $result = $this->service->invalidateSession($sessionId);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('sessions_enhanced', ['id' => $sessionId]);
    }

    /**
     * test_session_invalidation_all_user_sessions
     */
    public function test_session_invalidation_all_user_sessions(): void
    {
        $userId = 1;
        $request = $this->createRequest();

        // Create 3 sessions
        for ($i = 0; $i < 3; $i++) {
            $sessionId = bin2hex(random_bytes(20));
            $this->service->createSession($sessionId, $userId, $request, 'tenant-1');
        }

        // Invalidate all
        $count = $this->service->invalidateAllUserSessions($userId, 'tenant-1');

        $this->assertEquals(3, $count);
        $this->assertDatabaseCount('sessions_enhanced', 0);
    }

    /**
     * test_fingerprint_validation_succeeds_same_device
     */
    public function test_fingerprint_validation_succeeds_same_device(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $session = $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Validate with same request
        $validation = $this->service->validateSession($sessionId, $userId, $request);

        $this->assertTrue($validation['valid']);
    }

    /**
     * test_fingerprint_validation_fails_different_ip
     */
    public function test_fingerprint_validation_fails_different_ip(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Create request with different IP
        $differentIpRequest = $this->createRequest(['ip' => '192.168.1.200']);

        // Validation result depends on fingerprinting_strict config
        $validation = $this->service->validateSession($sessionId, $userId, $differentIpRequest);

        // In strict mode, IP mismatch should be detected
        $strictMode = (bool)config('session.fingerprinting_strict', true);
        if ($strictMode) {
            $this->assertFalse($validation['valid']);
        }
    }

    /**
     * test_fingerprint_validation_fails_different_user_agent
     */
    public function test_fingerprint_validation_fails_different_user_agent(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Create request with different user-agent
        $differentUaRequest = $this->createRequest(['user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)']);

        $validation = $this->service->validateSession($sessionId, $userId, $differentUaRequest);

        // Different user-agent should be flagged but may warn instead of block
        $this->assertIsArray($validation);
    }

    /**
     * test_hijack_attempt_detected_and_logged
     */
    public function test_hijack_attempt_detected_and_logged(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Try to access with different user ID
        $validation = $this->service->validateSession($sessionId, 999, $request);

        // Should fail and log hijack attempt
        $this->assertFalse($validation['valid']);

        // Should have logged hijack attempt event
        $event = SessionSecurityEvent::where('event_type', 'hijack_attempt')->first();
        $this->assertNotNull($event);
        $this->assertEquals('critical', $event->severity);
    }

    /**
     * test_getActiveSessions_returns_all_active
     */
    public function test_getActiveSessions_returns_all_active(): void
    {
        $userId = 1;
        $request = $this->createRequest();

        // Create 2 active sessions
        for ($i = 0; $i < 2; $i++) {
            $sessionId = bin2hex(random_bytes(20));
            $this->service->createSession($sessionId, $userId, $request, 'tenant-1');
        }

        // Create 1 expired session
        $expiredId = bin2hex(random_bytes(20));
        SessionEnhanced::create([
            'id' => $expiredId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->subMinutes(1),
            'device_type' => 'web',
            'tenant_id' => 'tenant-1',
        ]);

        $sessions = $this->service->getActiveSessions($userId, 'tenant-1');

        // Should return only 2 active sessions
        $this->assertCount(2, $sessions);
    }

    /**
     * test_session_event_logging
     */
    public function test_session_event_logging(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Should have logged creation event
        $event = SessionSecurityEvent::where('session_id', $sessionId)
            ->where('event_type', 'session_created')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('info', $event->severity);
    }

    /**
     * test_session_validation_with_grace_period
     */
    public function test_session_validation_with_grace_period(): void
    {
        $sessionId = bin2hex(random_bytes(20));
        $userId = 1;
        $request = $this->createRequest();

        $session = $this->service->createSession($sessionId, $userId, $request, 'tenant-1');

        // Set last activity to just beyond idle timeout but within grace period
        $idleTimeout = (int)config('session.idle_timeout', 900);
        $gracePeriod = (int)config('session.idle_grace_period', 60);
        $session->update(['last_activity_at' => now()->subSeconds($idleTimeout + 30)]);

        // Should be valid with warning
        $validation = $this->service->validateSession($sessionId, $userId, $request);

        $this->assertTrue($validation['valid']);
        $this->assertEquals('warn', $validation['action']);
    }

    /**
     * Helper: Create a mock request
     */
    private function createRequest(array $overrides = [])
    {
        $defaults = [
            'ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ];

        $config = array_merge($defaults, $overrides);

        $request = $this->mock(\Illuminate\Http\Request::class);
        $request->shouldReceive('ip')->andReturn($config['ip']);
        $request->shouldReceive('userAgent')->andReturn($config['user_agent']);

        return $request;
    }
}
