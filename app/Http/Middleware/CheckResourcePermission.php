<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckResourcePermission
{
    public function handle(Request $request, Closure $next, string $resource, string $action = 'view'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admin can do anything
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $module = $this->extractModuleFromRoute($request);
        if (!$module) {
            return $next($request);
        }

        $permission = "{$module}.{$resource}.{$action}";

        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => "Insufficient permissions for action: {$action}",
                'permission' => $permission,
            ], 403);
        }

        return $next($request);
    }

    private function extractModuleFromRoute(Request $request): ?string
    {
        $path = $request->path();

        if (preg_match('#(?:api/v1/|/)([a-z-]+)#', $path, $matches)) {
            return str_replace('-', '', $matches[1]);
        }

        return null;
    }
}
