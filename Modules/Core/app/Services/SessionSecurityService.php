<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Models\SessionEnhanced;
use Modules\Core\Models\SessionSecurityEvent;

/**
 * Session Security Service
 *
 * Comprehensive session lifecycle management with security hardening:
 *
 * Core Features:
 * 1. Session Fixation Prevention: Token regeneration on login
 * 2. Session Hijacking Detection: IP/Device fingerprinting
 * 3. Concurrent Session Limits: Prevent multi-device abuse
 * 4. Activity Tracking: Last activity timestamps
 * 5. Timeout Enforcement: Session and idle timeouts
 * 6. Grace Period: Allow quick return after idle timeout
 * 7. Session Invalidation: Critical operation trigger
 * 8. Multi-tenant Isolation: Per-company session isolation
 *
 * Configuration (config/session.php):
 * - session_timeout: Total session lifetime (3600s)
 * - idle_timeout: Inactivity timeout (900s)
 * - idle_grace_period: Recovery window (60s)
 * - concurrent_session_limit: Max sessions per user (3)
 * - regenerate_on_login: Always regenerate on auth
 * - regenerate_every_requests: ID change frequency (50)
 * - regenerate_every_minutes: Time-based regeneration (15)
 * - fingerprinting_enabled: Enable device fingerprinting
 * - fingerprinting_strict: Block vs warn on mismatch
 *
 * Performance Targets:
 * - Session validation: <2ms
 * - Fingerprint validation: <1ms
 * - Activity recording: <1ms
 * - Cache hit rate: >95%
 */
class SessionSecurityService
{
    private SessionFingerprint $fingerprint;
    private int $sessionTimeout;
    private int $idleTimeout;
    private int $idleGracePeriod;
    private int $concurrentSessionLimit;
    private bool $fingerprintingEnabled;
    private bool $fingerprintingStrict;
    private bool $regenerateOnLogin;
    private int $regenerateEveryRequests;
    private int $regenerateEveryMinutes;
    private bool $ipBinding;
    private bool $userAgentBinding;

    public function __construct(SessionFingerprint $fingerprint)
    {
        $this->fingerprint = $fingerprint;

        // Load configuration
        $this->sessionTimeout = (int)config('session.session_timeout', 3600);
        $this->idleTimeout = (int)config('session.idle_timeout', 900);
        $this->idleGracePeriod = (int)config('session.idle_grace_period', 60);
        $this->concurrentSessionLimit = (int)config('session.concurrent_session_limit', 3);
        $this->fingerprintingEnabled = (bool)config('session.fingerprinting_enabled', true);
        $this->fingerprintingStrict = (bool)config('session.fingerprinting_strict', true);
        $this->regenerateOnLogin = (bool)config('session.regenerate_on_login', true);
        $this->regenerateEveryRequests = (int)config('session.regenerate_every_requests', 50);
        $this->regenerateEveryMinutes = (int)config('session.regenerate_every_minutes', 15);
        $this->ipBinding = (bool)config('session.ip_binding', true);
        $this->userAgentBinding = (bool)config('session.user_agent_binding', true);
    }

    /**
     * Create a new session with security metadata
     *
     * @param string $sessionId Session ID
     * @param int|string|null $userId User ID
     * @param Request $request Current request
     * @param string|null $tenantId Tenant ID
     * @return SessionEnhanced
     */
    public function createSession(
        string $sessionId,
        int|string|null $userId,
        Request $request,
        ?string $tenantId = null
    ): SessionEnhanced {
        $deviceType = $this->fingerprint->getDeviceType($request->userAgent() ?? '');
        $fingerprint = $this->fingerprintingEnabled
            ? $this->fingerprint->generateFingerprint($request)
            : null;

        $userAgentHash = $this->userAgentBinding && $request->userAgent()
            ? hash('sha256', $request->userAgent())
            : null;

        $deviceFingerprint = $this->fingerprintingEnabled
            ? hash('sha256', $deviceType . '|' . ($userAgentHash ?? ''))
            : null;

        $session = SessionEnhanced::create([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $request->ip() ?? 'unknown',
            'user_agent_hash' => $userAgentHash,
            'device_fingerprint' => $deviceFingerprint,
            'browser_fingerprint' => $fingerprint,
            'device_type' => $deviceType,
            'created_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addSeconds($this->sessionTimeout),
            'regeneration_count' => 0,
            'concurrent_session_number' => $this->getNextConcurrentNumber($userId, $tenantId),
            'suspicious_activity_count' => 0,
            'tenant_id' => $tenantId,
        ]);

        // Log session creation event
        $this->logSecurityEvent(
            $sessionId,
            $userId,
            'session_created',
            $request->ip() ?? 'unknown',
            reason: 'New session established',
            severity: 'info'
        );

        // Invalidate active sessions cache
        $this->invalidateActiveSessionsCache($userId, $tenantId);

        return $session;
    }

    /**
     * Regenerate session ID for fixation prevention
     *
     * Creates a new session ID and invalidates the old one,
     * preserving user context and security metadata.
     *
     * @param string $oldSessionId Current session ID
     * @param int|string|null $userId User ID
     * @param Request $request Current request
     * @param string|null $tenantId Tenant ID
     * @return string New session ID
     */
    public function regenerateSessionId(
        string $oldSessionId,
        int|string|null $userId,
        Request $request,
        ?string $tenantId = null
    ): string {
        // Get old session
        $oldSession = SessionEnhanced::find($oldSessionId);

        if (!$oldSession) {
            // Create new session if old doesn't exist
            $newSessionId = $this->generateSecureSessionId();
            $this->createSession($newSessionId, $userId, $request, $tenantId);
            return $newSessionId;
        }

        // Generate new session ID
        $newSessionId = $this->generateSecureSessionId();

        // Create new session record
        $newSession = SessionEnhanced::create([
            'id' => $newSessionId,
            'user_id' => $userId ?? $oldSession->user_id,
            'ip_address' => $request->ip() ?? $oldSession->ip_address,
            'user_agent_hash' => $oldSession->user_agent_hash,
            'device_fingerprint' => $oldSession->device_fingerprint,
            'browser_fingerprint' => $oldSession->browser_fingerprint,
            'device_type' => $oldSession->device_type,
            'created_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => $oldSession->expires_at, // Preserve expiration
            'regeneration_count' => $oldSession->regeneration_count + 1,
            'concurrent_session_number' => $oldSession->concurrent_session_number,
            'suspicious_activity_count' => $oldSession->suspicious_activity_count,
            'tenant_id' => $tenantId ?? $oldSession->tenant_id,
        ]);

        // Delete old session
        $oldSession->delete();

        // Log regeneration event
        $this->logSecurityEvent(
            $newSessionId,
            $userId,
            'regeneration',
            $request->ip() ?? 'unknown',
            reason: 'Session ID regenerated for security',
            severity: 'info'
        );

        // Invalidate cache
        $this->invalidateActiveSessionsCache($userId, $tenantId);

        return $newSessionId;
    }

    /**
     * Validate an active session
     *
     * Comprehensive validation checking:
     * - Existence in database
     * - Expiration time
     * - Idle timeout
     * - Fingerprint match (if enabled)
     * - Concurrent session limits
     *
     * @param string $sessionId Session ID
     * @param int|string|null $userId Expected user ID (optional)
     * @param Request $request Current request
     * @return array{
     *   valid: bool,
     *   reason: string,
     *   action: 'allow'|'invalidate'|'warn',
     *   session: SessionEnhanced|null,
     *   details: array
     * }
     */
    public function validateSession(
        string $sessionId,
        int|string|null $userId = null,
        Request $request = null
    ): array {
        $session = SessionEnhanced::find($sessionId);

        if (!$session) {
            return [
                'valid' => false,
                'reason' => 'Session not found',
                'action' => 'invalidate',
                'session' => null,
                'details' => [],
            ];
        }

        // Check user match
        if ($userId && $session->user_id && $session->user_id !== $userId) {
            $this->logSecurityEvent(
                $sessionId,
                $session->user_id,
                'hijack_attempt',
                $request->ip() ?? 'unknown',
                reason: 'Session accessed by different user',
                severity: 'critical',
                actionTaken: 'invalidate'
            );
            $session->delete();

            return [
                'valid' => false,
                'reason' => 'Session user mismatch (potential hijacking)',
                'action' => 'invalidate',
                'session' => null,
                'details' => ['mismatched_user' => true],
            ];
        }

        // Check expiration
        if ($session->isExpired()) {
            $this->logSecurityEvent(
                $sessionId,
                $session->user_id,
                'timeout',
                $request->ip() ?? 'unknown',
                reason: 'Session expired',
                severity: 'info'
            );

            return [
                'valid' => false,
                'reason' => 'Session expired',
                'action' => 'invalidate',
                'session' => $session,
                'details' => ['expired' => true],
            ];
        }

        // Check idle timeout with grace period
        $idleDetails = $this->checkIdleTimeout($session);
        if (!$idleDetails['valid']) {
            return [
                'valid' => false,
                'reason' => $idleDetails['reason'],
                'action' => $idleDetails['action'],
                'session' => $session,
                'details' => $idleDetails,
            ];
        }

        // Check fingerprint if enabled and request provided
        if ($this->fingerprintingEnabled && $request) {
            $fingerprintResult = $this->validateSessionFingerprint($session, $request);
            if (!$fingerprintResult['valid']) {
                return [
                    'valid' => false,
                    'reason' => $fingerprintResult['reason'],
                    'action' => $fingerprintResult['action'],
                    'session' => $session,
                    'details' => $fingerprintResult,
                ];
            }
        }

        // All checks passed. If the session was idle but within the grace period,
        // surface the warning so callers can prompt re-authentication.
        $idleWithinGrace = $idleDetails['idle'] ?? false;

        return [
            'valid' => true,
            'reason' => $idleWithinGrace ? $idleDetails['reason'] : 'Session valid',
            'action' => $idleWithinGrace ? 'warn' : 'allow',
            'session' => $session,
            'details' => $idleWithinGrace ? $idleDetails : [],
        ];
    }

    /**
     * Check idle timeout with grace period
     *
     * Grace period allows quick return without full re-authentication.
     * If within grace period of becoming idle, allows access but warns.
     *
     * @param SessionEnhanced $session
     * @return array{valid: bool, reason: string, action: string, idle: bool}
     */
    private function checkIdleTimeout(SessionEnhanced $session): array
    {
        if ($session->isIdle($this->idleTimeout / 60)) {
            // abs: Carbon 3 returns a signed (negative) diff for past timestamps
            $timeSinceActive = abs(now()->diffInSeconds($session->last_activity_at));

            // Check if within grace period
            if ($timeSinceActive <= $this->idleTimeout + $this->idleGracePeriod) {
                $this->logSecurityEvent(
                    $session->id,
                    $session->user_id,
                    'idle',
                    null,
                    reason: 'Session idle but within grace period',
                    severity: 'warning',
                    actionTaken: 'warn'
                );

                return [
                    'valid' => true,
                    'reason' => 'Session idle but grace period active',
                    'action' => 'warn',
                    'idle' => true,
                ];
            }

            // Grace period expired, session is idle
            $this->logSecurityEvent(
                $session->id,
                $session->user_id,
                'idle',
                null,
                reason: 'Session idle timeout exceeded',
                severity: 'warning',
                actionTaken: 'invalidate'
            );

            return [
                'valid' => false,
                'reason' => 'Session idle timeout exceeded',
                'action' => 'invalidate',
                'idle' => true,
            ];
        }

        return [
            'valid' => true,
            'reason' => 'Session activity within timeout window',
            'action' => 'allow',
            'idle' => false,
        ];
    }

    /**
     * Validate session fingerprint
     *
     * @param SessionEnhanced $session
     * @param Request $request
     * @return array{valid: bool, reason: string, action: string, fingerprint_match: bool}
     */
    private function validateSessionFingerprint(
        SessionEnhanced $session,
        Request $request
    ): array {
        $currentFingerprint = $this->fingerprint->generateFingerprint($request);

        if (!hash_equals($session->browser_fingerprint ?? '', $currentFingerprint)) {
            // Fingerprint mismatch
            $validationResult = $this->fingerprint->validateFingerprint(
                $session->browser_fingerprint ?? '',
                $request,
                $this->fingerprintingStrict
            );

            if ($this->fingerprintingStrict && !$validationResult['valid']) {
                $this->logSecurityEvent(
                    $session->id,
                    $session->user_id,
                    'fingerprint_mismatch',
                    $request->ip() ?? 'unknown',
                    $session->browser_fingerprint,
                    $currentFingerprint,
                    'Device fingerprint mismatch (hijacking attempt)',
                    'critical',
                    'invalidate'
                );

                return [
                    'valid' => false,
                    'reason' => 'Session fingerprint mismatch (hijacking attempt detected)',
                    'action' => 'invalidate',
                    'fingerprint_match' => false,
                ];
            }

            // Warn mode
            if (!$validationResult['valid']) {
                $this->logSecurityEvent(
                    $session->id,
                    $session->user_id,
                    'fingerprint_mismatch',
                    $request->ip() ?? 'unknown',
                    $session->browser_fingerprint,
                    $currentFingerprint,
                    'Device fingerprint mismatch (warn mode)',
                    'warning',
                    'warn'
                );

                return [
                    'valid' => true,
                    'reason' => 'Fingerprint mismatch detected (warn mode)',
                    'action' => 'warn',
                    'fingerprint_match' => false,
                ];
            }
        }

        // Update fingerprint check timestamp
        $session->update(['fingerprint_checked_at' => now()]);

        return [
            'valid' => true,
            'reason' => 'Fingerprint valid',
            'action' => 'allow',
            'fingerprint_match' => true,
        ];
    }

    /**
     * Record session activity (update last_activity_at)
     *
     * Called on each valid request to track activity.
     * Performance-optimized: batches updates, uses database directly.
     *
     * @param string $sessionId Session ID
     * @return bool
     */
    public function recordActivity(string $sessionId): bool
    {
        try {
            SessionEnhanced::where('id', $sessionId)->update([
                'last_activity_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::warning('Failed to record session activity', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Enforce concurrent session limits for a user
     *
     * Prevents user from exceeding max concurrent sessions.
     * If limit exceeded, invalidates oldest session(s).
     *
     * @param int|string $userId User ID
     * @param int|null $maxSessions Override limit (null = use config)
     * @param string|null $tenantId Tenant ID
     * @return array{limited: bool, invalidated_count: int, reason: string}
     */
    public function enforceConcurrentLimit(
        int|string $userId,
        ?int $maxSessions = null,
        ?string $tenantId = null
    ): array {
        $maxSessions = $maxSessions ?? $this->concurrentSessionLimit;

        // Get active sessions
        $activeSessions = SessionEnhanced::forUser($userId)
            ->forTenant($tenantId)
            ->active()
            ->orderBy('last_activity_at', 'asc')
            ->get();

        if ($activeSessions->count() <= $maxSessions) {
            return [
                'limited' => false,
                'invalidated_count' => 0,
                'reason' => 'Concurrent limit not exceeded',
            ];
        }

        // Invalidate oldest sessions
        $toInvalidate = $activeSessions->count() - $maxSessions;
        $invalidated = 0;

        foreach ($activeSessions->take($toInvalidate) as $session) {
            $this->invalidateSession($session->id, 'Concurrent session limit exceeded');
            $invalidated++;
        }

        $this->logSecurityEvent(
            null,
            $userId,
            'concurrent_limit',
            null,
            reason: "Invalidated $invalidated sessions due to concurrent limit",
            severity: 'warning',
            actionTaken: 'invalidate'
        );

        return [
            'limited' => true,
            'invalidated_count' => $invalidated,
            'reason' => "Exceeded concurrent session limit ($maxSessions)",
        ];
    }

    /**
     * Enforce session timeout
     *
     * Checks if session has exceeded total lifetime (session_timeout).
     *
     * @param string $sessionId Session ID
     * @return bool True if session is valid (not timed out)
     */
    public function enforceSessionTimeout(string $sessionId): bool
    {
        $session = SessionEnhanced::find($sessionId);

        if (!$session || $session->isExpired()) {
            if ($session) {
                $this->invalidateSession($sessionId, 'Session timeout exceeded');
            }
            return false;
        }

        return true;
    }

    /**
     * Check if session is idle
     *
     * @param string $sessionId Session ID
     * @param int $timeoutMinutes Idle timeout in minutes (null = use config)
     * @return bool True if session is idle
     */
    public function isSessionIdle(string $sessionId, ?int $timeoutMinutes = null): bool
    {
        $session = SessionEnhanced::find($sessionId);

        if (!$session) {
            return true; // Non-existent session is "idle"
        }

        $timeoutMinutes = $timeoutMinutes ?? ($this->idleTimeout / 60);
        return $session->isIdle($timeoutMinutes);
    }

    /**
     * Invalidate a single session
     *
     * Removes session from database and logs the event.
     *
     * @param string $sessionId Session ID
     * @param string|null $reason Reason for invalidation
     * @return bool
     */
    public function invalidateSession(string $sessionId, ?string $reason = null): bool
    {
        $session = SessionEnhanced::find($sessionId);

        if (!$session) {
            return false;
        }

        $userId = $session->user_id;
        $tenantId = $session->tenant_id;

        // Log event before deletion
        $this->logSecurityEvent(
            $sessionId,
            $userId,
            'session_invalidated',
            null,
            reason: $reason ?? 'Session invalidated',
            severity: 'info',
            actionTaken: 'invalidate'
        );

        // Delete session
        $session->delete();

        // Invalidate cache
        $this->invalidateActiveSessionsCache($userId, $tenantId);

        return true;
    }

    /**
     * Invalidate all sessions for a user
     *
     * Used for logout, password change, security incident response.
     *
     * @param int|string $userId User ID
     * @param string|null $tenantId Tenant ID
     * @param string|null $reason Reason for invalidation
     * @return int Number of sessions invalidated
     */
    public function invalidateAllUserSessions(
        int|string $userId,
        ?string $tenantId = null,
        ?string $reason = null
    ): int {
        $sessions = SessionEnhanced::forUser($userId)
            ->forTenant($tenantId)
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            if ($this->invalidateSession($session->id, $reason ?? 'All user sessions invalidated')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get all active sessions for a user
     *
     * Uses cache with 1-minute TTL for performance.
     *
     * @param int|string $userId User ID
     * @param string|null $tenantId Tenant ID
     * @return array
     */
    public function getActiveSessions(int|string $userId, ?string $tenantId = null): array
    {
        $cacheKey = "sessions:user:{$userId}:active" . ($tenantId ? ":{$tenantId}" : '');

        return Cache::remember($cacheKey, 60, function () use ($userId, $tenantId) {
            return SessionEnhanced::forUser($userId)
                ->forTenant($tenantId)
                ->active()
                ->orderBy('last_activity_at', 'desc')
                ->get()
                ->map(function (SessionEnhanced $session) {
                    return [
                        'id' => $session->id,
                        'device_type' => $session->device_type,
                        'ip_address' => $this->maskIpAddress($session->ip_address),
                        'created_at' => $session->created_at->toIso8601String(),
                        'last_activity_at' => $session->last_activity_at->toIso8601String(),
                        'expires_at' => $session->expires_at->toIso8601String(),
                        'time_remaining' => $session->timeUntilExpiration(),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Log a security event
     *
     * Records session security events for audit trail and monitoring.
     *
     * @param string|null $sessionId Session ID
     * @param int|string|null $userId User ID
     * @param string $eventType Event type
     * @param string|null $ipAddress IP address
     * @param string|null $oldFingerprint Previous fingerprint
     * @param string|null $newFingerprint Current fingerprint
     * @param string|null $reason Reason/details
     * @param string $severity Severity level
     * @param string $actionTaken Action taken
     * @return void
     */
    private function logSecurityEvent(
        ?string $sessionId,
        int|string|null $userId,
        string $eventType,
        ?string $ipAddress = null,
        ?string $oldFingerprint = null,
        ?string $newFingerprint = null,
        ?string $reason = null,
        string $severity = 'info',
        string $actionTaken = 'none'
    ): void {
        try {
            SessionSecurityEvent::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'event_type' => $eventType,
                'ip_address' => $ipAddress,
                'old_fingerprint' => $oldFingerprint,
                'new_fingerprint' => $newFingerprint,
                'reason' => $reason,
                'severity' => $severity,
                'action_taken' => $actionTaken,
                'created_at' => now(),
                'tenant_id' => tenancy()->tenant?->getTenantKey(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to log session security event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a secure session ID
     *
     * Uses cryptographically secure random bytes (40 bytes = 320 bits).
     *
     * @return string Session ID (80 hex characters)
     */
    private function generateSecureSessionId(): string
    {
        return bin2hex(random_bytes(40));
    }

    /**
     * Get next concurrent session number for a user
     *
     * @param int|string|null $userId User ID
     * @param string|null $tenantId Tenant ID
     * @return int
     */
    private function getNextConcurrentNumber(int|string|null $userId, ?string $tenantId = null): int
    {
        if (!$userId) {
            return 1;
        }

        $maxNumber = SessionEnhanced::forUser($userId)
            ->forTenant($tenantId)
            ->max('concurrent_session_number') ?? 0;

        return $maxNumber + 1;
    }

    /**
     * Invalidate active sessions cache for a user
     *
     * @param int|string|null $userId User ID
     * @param string|null $tenantId Tenant ID
     * @return void
     */
    private function invalidateActiveSessionsCache(int|string|null $userId, ?string $tenantId = null): void
    {
        if (!$userId) {
            return;
        }

        $cacheKey = "sessions:user:{$userId}:active" . ($tenantId ? ":{$tenantId}" : '');
        Cache::forget($cacheKey);
    }

    /**
     * Mask IP address for logging (show first 3 octets only)
     *
     * @param string $ip IP address
     * @return string Masked IP
     */
    private function maskIpAddress(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = '***';
            return implode('.', $parts);
        }
        return $ip; // IPv6 or non-standard format
    }
};
