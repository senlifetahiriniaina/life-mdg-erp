<?php

declare(strict_types=1);

namespace Modules\Messaging\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Policies\ConversationPolicy;
use Nwidart\Modules\Traits\PathNamespace;

class MessagingServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Messaging';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        // Modules-namespaced policies don't auto-discover the way App\Policies
        // ones do — same precedent as Core/BI/HR/Payroll/Strategy/Calendar.
        Gate::policy(Conversation::class, ConversationPolicy::class);

        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
