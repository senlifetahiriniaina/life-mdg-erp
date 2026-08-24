<?php

declare(strict_types=1);

namespace Modules\Security\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Security\Listeners\RecordAuthenticationEvent;

/**
 * Chantier 32.3: gives Modules\Security\Models\AuthenticationEvent a real
 * producer — see RecordAuthenticationEvent's own docblock. Coexists with
 * Modules\Core\Providers\EventServiceProvider's AuditAuthListener on the
 * same 3 events (Laravel merges every registered provider's $listen array).
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [RecordAuthenticationEvent::class.'@handleLogin'],
        Logout::class => [RecordAuthenticationEvent::class.'@handleLogout'],
        Failed::class => [RecordAuthenticationEvent::class.'@handleFailed'],
    ];

    protected static $shouldDiscoverEvents = false;
}
