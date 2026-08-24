<?php

declare(strict_types=1);

namespace Modules\Security\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Modules\Security\Services\SecurityAuditService;

/**
 * Chantier 32.3 (14-layer deep audit): Modules\Security\Models\AuthenticationEvent
 * (the module's zero-trust auth-event log — richer than the generic audit
 * trail, with authentication_method/device_info/status/failure_reason/
 * trust_score/risk_factors columns) is a real, migrated, routed (see
 * AuthenticationEventController — index/summary/suspiciousActivity, all
 * security-admin-gated) model with a real write method
 * (SecurityAuditService::recordAuthEvent()) that had zero producers
 * anywhere in the app — confirmed via grep before this fix. Every real
 * login/logout/failed-login in this app instead only ever landed in the
 * separate, generic Modules\Core\Models\AuditLog (via
 * Modules\Core\Listeners\AuditAuthListener, on the same 3 Laravel auth
 * events) — that listener is untouched and keeps running exactly as
 * before; Laravel merges $listen arrays across every registered
 * EventServiceProvider, so both fire independently on the same events.
 *
 * trust_score/risk_factors/device_info are deliberately left null — actually
 * scoring risk per login is a real zero-trust feature this app has never
 * built (no producer anywhere, and building one is a genuinely new piece of
 * business logic, not a wiring fix) — documented as a gap rather than
 * guessed at.
 */
class RecordAuthenticationEvent
{
    public function __construct(private readonly SecurityAuditService $auditService) {}

    public function handleLogin(Login $event): void
    {
        if (! ($event->user instanceof User)) {
            return;
        }

        $this->record($event->user, 'login', 'success');
    }

    public function handleLogout(Logout $event): void
    {
        if (! ($event->user instanceof User)) {
            return;
        }

        $this->record($event->user, 'logout', 'success');
    }

    public function handleFailed(Failed $event): void
    {
        try {
            $credentials = $event->credentials;
            $email = is_array($credentials) ? ($credentials['email'] ?? 'unknown') : 'unknown';

            $this->auditService->recordAuthEvent([
                'user_id'               => null,
                'user_email'            => (string) $email,
                'event_type'            => 'failed',
                'authentication_method' => 'password',
                'ip_address'            => request()->ip() ?? '0.0.0.0',
                'user_agent'            => request()->userAgent(),
                'status'                => 'failure',
                'failure_reason'        => 'invalid_credentials',
                'authenticated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Never let audit-event recording break the real login flow.
        }
    }

    private function record(User $user, string $eventType, string $status): void
    {
        try {
            $this->auditService->recordAuthEvent([
                'user_id'               => $user->id,
                'user_email'            => (string) $user->email,
                'event_type'            => $eventType,
                'authentication_method' => $user->two_factor_enabled ? 'mfa' : 'password',
                'ip_address'            => request()->ip() ?? '0.0.0.0',
                'user_agent'            => request()->userAgent(),
                'status'                => $status,
                'authenticated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Never let audit-event recording break the real login flow.
        }
    }
}
