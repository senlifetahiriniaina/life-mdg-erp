<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Modules\AuditLog\Services\AuditService;

/**
 * Records authentication-security events (successful login, logout, failed
 * login, lockout) to the audit log. Privilege-change auditing is handled at the
 * RBAC mutation points (see RoleManagementController).
 */
class AuthEventSubscriber
{
    public function __construct(private AuditService $audit)
    {
    }

    public function handleLogin(Login $event): void
    {
        $this->record('auth.login', $event->user?->getAuthIdentifier(), $event->user->email ?? null);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('auth.logout', $event->user?->getAuthIdentifier(), $event->user->email ?? null);
    }

    public function handleFailed(Failed $event): void
    {
        $this->record('auth.failed', $event->user?->getAuthIdentifier(), $event->credentials['email'] ?? null);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->record('auth.lockout', null, $event->request->input('email'));
    }

    private function record(string $action, mixed $userId, ?string $email): void
    {
        try {
            $request = request();

            $this->audit->log([
                'tenant_id' => 0,
                'user_id' => is_numeric($userId) ? (int) $userId : null,
                'user_name' => $email,
                'module' => 'Auth',
                'action' => $action,
                'entity_type' => 'User',
                'entity_id' => is_numeric($userId) ? (int) $userId : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable) {
            // Auditing must never break authentication.
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }
}
