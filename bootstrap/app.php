<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Chantier 19 follow-up: this app previously kept an App\Console\Kernel
    // (Laravel 10-style) whose schedule() method was NEVER actually invoked —
    // Laravel 11+'s Application::configure() never binds
    // Illuminate\Contracts\Console\Kernel to a custom Kernel class unless told
    // to (that's a deliberate deprecation of the old style in favor of this
    // fluent bootstrap/app.php configuration). `php artisan schedule:list`
    // only ever showed the 2 jobs registered via the callAfterResolving()
    // pattern in Modules\Analytics\Providers\AnalyticsServiceProvider and
    // Modules\Helpdesk\Providers\HelpdeskServiceProvider — none of the 4 real
    // jobs below (confirmed empirically via `php artisan schedule:list`
    // before this fix showing only those 2). This is the one place a
    // schedule actually gets registered in this app now; the dead
    // App\Console\Kernel class (its commands() method was also a no-op next
    // to it, since Application::configure() already auto-discovers
    // app/Console/Commands via its own default withCommands() call) has been
    // deleted.
    ->withSchedule(function (Schedule $schedule) {
        // Refresh materialized views every 4 hours for analytics/reporting (00:15, 04:15, 08:15, 12:15, 16:15, 20:15 UTC)
        $schedule->command('materialized-views:refresh')
            ->everyFourHours()
            ->timezone('UTC')
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Materialized views refresh failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Materialized views refreshed successfully');
            });

        // Expire overdue tenant sandbox environments daily
        $schedule->command('core:expire-sandboxes')->daily();

        // Daily compressed database backup (data + schema manifest, see
        // BackupDatabase/SchemaSnapshotService) at 02:00 UTC. --s3 forces
        // S3 regardless of config('backup.disk') — matches this schedule's
        // existing intent (daily backups always go off-box).
        $schedule->command('backup:database --s3')
            ->dailyAt('02:00')
            ->timezone('UTC')
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Database backup failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Database backup completed successfully');
            });

        // Hourly binary log backup for point-in-time recovery
        // (Optional: requires MySQL binary logging enabled)
        // $schedule->command('backup:binlog')
        //     ->hourly()
        //     ->timezone('UTC')
        //     ->withoutOverlapping();

        // Cleanup old backups — retention now driven by config('backup.retention_days')
        // (BACKUP_RETENTION_DAYS), not hardcoded here.
        $schedule->command('backup:cleanup')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\CacheHeaders::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\RequestInspectionMiddleware::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Required for the SPA's own axios calls to /api/v1/* routes guarded by
        // auth:sanctum: without this, Sanctum never recognizes the session cookie
        // and falls back to token-only auth, rejecting every same-origin request
        // with 401 "Unauthenticated." regardless of a valid web login.
        $middleware->statefulApi();

        $middleware->api(append: [
            \App\Http\Middleware\CacheHeaders::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\RequestInspectionMiddleware::class,
            \App\Http\Middleware\RequestTiming::class,
        ]);

        $middleware->alias([
            'module'     => \Modules\Core\Http\Middleware\CheckModuleEnabled::class,
            'role'       => \App\Http\Middleware\RequireRole::class,
            'n+1.detect' => \App\Http\Middleware\DetectNPlusOne::class,
            'cache.api'  => \App\Http\Middleware\CacheApiResponse::class,
            'check-module-access' => \App\Http\Middleware\CheckModuleAccess::class,
            'check-resource' => \App\Http\Middleware\CheckResourcePermission::class,
            '2fa'        => \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
            'session.security' => \App\Http\Middleware\SanctumSessionSecurity::class,
            'tenancy.user' => \App\Http\Middleware\InitializeTenancyFromAuthenticatedUser::class,
            'abilities'  => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'    => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            $request = request();

            // API requests must always get JSON — never the Inertia error page
            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            if (in_array($response->getStatusCode(), [403, 404, 500, 503]) && ! app()->runningInConsole()) {
                return Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
