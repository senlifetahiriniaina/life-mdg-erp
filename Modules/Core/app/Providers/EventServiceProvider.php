<?php

namespace Modules\Core\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Listeners\AuditAuthListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [AuditAuthListener::class.'@handleLogin'],
        Logout::class => [AuditAuthListener::class.'@handleLogout'],
        Failed::class => [AuditAuthListener::class.'@handleFailed'],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}
