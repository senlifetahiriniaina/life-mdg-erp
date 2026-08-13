<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate specific API routes to users holding at least one of the required roles.
 *
 * Usage in routes:  ->middleware('role:admin,hr-manager')
 *
 * super-admin and admin always pass. This middleware handles coarse-grained
 * module-level RBAC so individual controllers don't all need to duplicate
 * role checks.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // super-admin and admin bypass everything (matches Gate::before in AppServiceProvider)
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $next($request);
        }

        if (! empty($roles) && ! $user->hasAnyRole($roles)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}
