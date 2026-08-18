<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Request;
use Modules\Core\Models\AuditLog;

class AuditAuthListener
{
    public function handleLogin(Login $event): void
    {
        try {
            if (! ($event->user instanceof User)) {
                return;
            }

            $user = $event->user;

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? 0,
                'user_name' => $user->name,
                'user_role' => $user->getRoleNames()->first(),
                'action' => 'login',
                'module' => 'Auth',
                'event_type' => 'login',
                'description' => "Connexion de {$user->name}",
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
        }
    }

    public function handleLogout(Logout $event): void
    {
        try {
            if (! ($event->user instanceof User)) {
                return;
            }

            $user = $event->user;

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? 0,
                'user_name' => $user->name,
                'user_role' => $user->getRoleNames()->first(),
                'action' => 'logout',
                'module' => 'Auth',
                'event_type' => 'logout',
                'description' => "Déconnexion de {$user->name}",
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
        }
    }

    public function handleFailed(Failed $event): void
    {
        try {
            $credentials = $event->credentials;
            /** @phpstan-ignore function.alreadyNarrowedType */
            $email = is_array($credentials) ? ($credentials['email'] ?? 'unknown') : 'unknown';

            AuditLog::create([
                'user_id' => null,
                'company_id' => 0,
                'user_name' => $email,
                'action' => 'login_failed',
                'module' => 'Auth',
                'event_type' => 'login_failed',
                'description' => "Tentative de connexion échouée pour {$email}",
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
        }
    }
}
