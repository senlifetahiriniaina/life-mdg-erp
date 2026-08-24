<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Models\SessionEnhanced;
use Modules\Core\Models\SessionSecurityEvent;

/**
 * Session Management Dashboard Service
 *
 * Provides user-facing session management functionality:
 * - View all active sessions with details
 * - Terminate individual sessions remotely
 * - Terminate all other sessions at once
 * - View session activity timeline
 * - Monitor suspicious activities
 *
 * Used for session management UI in user profile/security settings.
 */
class SessionManagementDashboard
{
    private SessionSecurityService $sessionSecurityService;

    public function __construct(SessionSecurityService $sessionSecurityService)
    {
        $this->sessionSecurityService = $sessionSecurityService;
    }

    /**
     * Get all active sessions for a user with detailed information
     *
     * @param int|string $userId User ID
     * @param string|null $tenantId Tenant ID
     * @return array
     */
    public function getActiveSessions(int|string $userId, ?string $tenantId = null): array
    {
        $sessions = SessionEnhanced::forUser($userId)
            ->forTenant($tenantId)
            ->active()
            ->orderBy('last_activity_at', 'desc')
            ->get();

        return $sessions->map(function (SessionEnhanced $session) {
            return $this->formatSessionInfo($session);
        })->toArray();
    }

    /**
     * Get detailed information about a specific session
     *
     * @param string $sessionId Session ID
     * @return array|null
     */
    public function getSessionDetails(string $sessionId): ?array
    {
        $session = SessionEnhanced::find($sessionId);

        if (!$session) {
            return null;
        }

        return $this->formatSessionInfo($session);
    }

    /**
     * Terminate a session (user-initiated)
     *
     * Allows a user to logout from a specific device/session.
     * Verifies ownership before terminating.
     *
     * @param string $sessionId Session ID
     * @param int|string $userId User ID (for verification)
     * @return array{success: bool, message: string}
     */
    public function terminateSession(string $sessionId, int|string $userId): array
    {
        $session = SessionEnhanced::find($sessionId);

        if (!$session) {
            return [
                'success' => false,
                'message' => 'Session not found',
            ];
        }

        // Verify ownership
        if ($session->user_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Unauthorized: Cannot terminate another user\'s session',
            ];
        }

        // Terminate
        $this->sessionSecurityService->invalidateSession(
            $sessionId,
            'User terminated session from account settings'
        );

        return [
            'success' => true,
            'message' => 'Session terminated successfully',
        ];
    }

    /**
     * Terminate all other sessions for a user
     *
     * Useful for security: user can logout all other devices at once.
     * Preserves the current session.
     *
     * @param int|string $userId User ID
     * @param string $currentSessionId Session ID to preserve (current session)
     * @param string|null $tenantId Tenant ID
     * @return array{success: bool, message: string, terminated_count: int}
     */
    public function terminateAllOtherSessions(
        int|string $userId,
        string $currentSessionId,
        ?string $tenantId = null
    ): array {
        $sessions = SessionEnhanced::forUser($userId)
            ->forTenant($tenantId)
            ->active()
            ->where('id', '!=', $currentSessionId)
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            $this->sessionSecurityService->invalidateSession(
                $session->id,
                'User terminated all other sessions from account settings'
            );
            $count++;
        }

        return [
            'success' => true,
            'message' => "Terminated $count session(s) on other devices",
            'terminated_count' => $count,
        ];
    }

    /**
     * Get session activity timeline
     *
     * Shows chronological activity for a specific session.
     *
     * @param string $sessionId Session ID
     * @param int $limit Maximum number of events
     * @return array
     */
    public function getSessionActivityTimeline(string $sessionId, int $limit = 50): array
    {
        $events = SessionSecurityEvent::where('session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $events->map(function (SessionSecurityEvent $event) {
            return [
                'id' => $event->id,
                'type' => $event->event_type,
                'description' => $this->getEventDescription($event),
                'severity' => $event->severity,
                'action' => $event->action_taken,
                'timestamp' => $event->created_at->toIso8601String(),
                'ip_address' => $event->ip_address ? $this->maskIpAddress($event->ip_address) : null,
                'reason' => $event->reason,
            ];
        })->toArray();
    }

    /**
     * Get suspicious activities for a user
     *
     * Shows security events that indicate potential security issues:
     * - Fingerprint mismatches
     * - Hijacking attempts
     * - Unusual IP addresses
     * - Multiple login attempts
     *
     * @param int|string $userId User ID
     * @param string|null $tenantId Tenant ID
     * @param int $limit Maximum number of events
     * @param int $hoursBack Look back N hours (default 24)
     * @return array
     */
    public function getSuspiciousActivities(
        int|string $userId,
        ?string $tenantId = null,
        int $limit = 100,
        int $hoursBack = 24
    ): array {
        $events = SessionSecurityEvent::forUser($userId)
            ->where(function ($query) {
                $query->where('severity', '!=', 'info')
                    ->orWhereIn('event_type', [
                        'fingerprint_mismatch',
                        'hijack_attempt',
                        'concurrent_limit',
                    ]);
            })
            ->where('created_at', '>=', now()->subHours($hoursBack))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($tenantId) {
            $events = $events->filter(fn($e) => $e->tenant_id === $tenantId);
        }

        return $events->map(function (SessionSecurityEvent $event) {
            return [
                'id' => $event->id,
                'session_id' => $event->session_id,
                'type' => $event->event_type,
                'description' => $this->getEventDescription($event),
                'severity' => $event->severity,
                'action' => $event->action_taken,
                'timestamp' => $event->created_at->toIso8601String(),
                'ip_address' => $event->ip_address ? $this->maskIpAddress($event->ip_address) : null,
                'reason' => $event->reason,
                'is_recent' => $event->created_at->diffInMinutes(now()) < 5,
            ];
        })->toArray();
    }

    /**
     * Get summary statistics for a user's sessions
     *
     * @param int|string $userId User ID
     * @param string|null $tenantId Tenant ID
     * @return array
     */
    public function getSessionSummary(int|string $userId, ?string $tenantId = null): array
    {
        // Chantier 38.1: materialize once into a real Collection before
        // calling groupBy()/map() on it — SessionEnhanced::forUser(...)->active()
        // returns a query Builder, and Builder::groupBy() sets a SQL clause
        // and still returns a Builder, not a Collection (Builder has no
        // map() method). This was never actually executed before this
        // chantier activated the dashboard via a real controller, so this
        // bug was dormant. Deriving all four summary values from one
        // materialized Collection avoids re-querying repeatedly too.
        $sessions = SessionEnhanced::forUser($userId)
            ->forTenant($tenantId)
            ->active()
            ->get();

        $totalActiveSessions = $sessions->count();
        $oldestSession = $sessions->sortBy('created_at')->first();
        $newestSession = $sessions->sortByDesc('created_at')->first();

        // Suspicious activities in last 24 hours
        $suspiciousCount = SessionSecurityEvent::forUser($userId)
            ->where('severity', '!=', 'info')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        // Device types
        $deviceBreakdown = $sessions->groupBy('device_type')
            ->map(fn($group) => $group->count())
            ->toArray();

        return [
            'total_active_sessions' => $totalActiveSessions,
            'oldest_session' => $oldestSession ? $oldestSession->created_at->toIso8601String() : null,
            'newest_session' => $newestSession ? $newestSession->created_at->toIso8601String() : null,
            'suspicious_activities_24h' => $suspiciousCount,
            'device_breakdown' => $deviceBreakdown,
        ];
    }

    /**
     * Format session information for display
     *
     * @param SessionEnhanced $session
     * @return array
     */
    private function formatSessionInfo(SessionEnhanced $session): array
    {
        $recentActivities = SessionSecurityEvent::where('session_id', $session->id)
            ->where('created_at', '>=', now()->subHours(1))
            ->count();

        return [
            'id' => $session->id,
            'device_type' => $session->device_type,
            'device_display' => $this->getDeviceDisplay($session->device_type),
            'ip_address' => $this->maskIpAddress($session->ip_address),
            'created_at' => $session->created_at->toIso8601String(),
            'last_activity_at' => $session->last_activity_at->toIso8601String(),
            'expires_at' => $session->expires_at->toIso8601String(),
            'time_remaining_seconds' => $session->timeUntilExpiration(),
            'time_remaining_display' => $this->formatTimeRemaining($session->timeUntilExpiration()),
            'is_current' => session('id') === $session->id, // If available in context
            'recent_activities' => $recentActivities,
            'regeneration_count' => $session->regeneration_count,
            'suspicious_activity_count' => $session->suspicious_activity_count,
        ];
    }

    /**
     * Get human-readable device display
     *
     * @param string $deviceType
     * @return string
     */
    private function getDeviceDisplay(string $deviceType): string
    {
        return match ($deviceType) {
            'mobile' => 'Mobile Phone',
            'tablet' => 'Tablet',
            'desktop' => 'Desktop',
            'web' => 'Web Browser',
            'bot' => 'Bot/Crawler',
            default => 'Unknown Device',
        };
    }

    /**
     * Format time remaining until session expiration
     *
     * @param int $seconds
     * @return string Human-readable format
     */
    private function formatTimeRemaining(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Expired';
        }

        if ($seconds < 60) {
            return "$seconds seconds";
        }

        $minutes = (int)($seconds / 60);
        if ($minutes < 60) {
            return "$minutes " . ($minutes === 1 ? 'minute' : 'minutes');
        }

        $hours = (int)($minutes / 60);
        if ($hours < 24) {
            return "$hours " . ($hours === 1 ? 'hour' : 'hours');
        }

        $days = (int)($hours / 24);
        return "$days " . ($days === 1 ? 'day' : 'days');
    }

    /**
     * Mask IP address for display
     *
     * @param string $ip
     * @return string
     */
    private function maskIpAddress(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = '***';
            return implode('.', $parts);
        }
        return $ip;
    }

    /**
     * Get human-readable event description
     *
     * @param SessionSecurityEvent $event
     * @return string
     */
    private function getEventDescription(SessionSecurityEvent $event): string
    {
        return match ($event->event_type) {
            'session_created' => 'Session created',
            'session_invalidated' => 'Session ended',
            'timeout' => 'Session expired due to timeout',
            'idle' => 'Session idle timeout',
            'regeneration' => 'Session ID regenerated',
            'fingerprint_mismatch' => 'Device fingerprint mismatch detected',
            'hijack_attempt' => 'Potential hijacking attempt detected',
            'concurrent_limit' => 'Concurrent session limit exceeded',
            default => 'Security event: ' . $event->event_type,
        };
    }
};
