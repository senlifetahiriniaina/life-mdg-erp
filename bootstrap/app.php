<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\CacheHeaders::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\CacheHeaders::class,
            \App\Http\Middleware\SecurityHeaders::class,
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
