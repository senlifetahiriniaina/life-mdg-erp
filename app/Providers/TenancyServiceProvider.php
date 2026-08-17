<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;

/**
 * Minimal stancl/tenancy service provider. Without this, none of the
 * bootstrappers configured in config/tenancy.php ever actually run
 * (tenancy()->tenant is still set by core Tenancy logic, but nothing
 * bootstraps cache/queue/redis/filesystem for the tenant context).
 *
 * Deliberately scoped down from the package's own stub: this app uses a
 * shared-DB + tenant_id scoping model (BelongsToTenant), not
 * database-per-tenant, so the TenantCreated/TenantDeleted job pipelines
 * are left empty (no CreateDatabase/MigrateDatabase jobs), and the
 * domain/subdomain/path tenancy-identification middleware from the
 * package stub is not registered here.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Events\TenancyInitialized::class, Listeners\BootstrapTenancy::class);
        Event::listen(Events\TenancyEnded::class, Listeners\RevertToCentralContext::class);
    }
}
