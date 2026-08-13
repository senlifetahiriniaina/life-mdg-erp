<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admin can access everything
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Extract module from route
        $module = $this->extractModuleFromRoute($request);

        if (!$module) {
            return $next($request);
        }

        // Check if user has any permission for this module
        if (!$this->userHasModuleAccess($user, $module)) {
            return response()->json([
                'message' => "Access denied to module: {$module}",
                'module' => $module,
            ], 403);
        }

        return $next($request);
    }

    private function extractModuleFromRoute(Request $request): ?string
    {
        $path = $request->path();

        // Match /api/v1/{module}/* or /{module}/*
        if (preg_match('#(?:api/v1/|/)([a-z-]+)#', $path, $matches)) {
            return str_replace('-', '', $matches[1]); // Handle kebab-case module names
        }

        return null;
    }

    private function userHasModuleAccess(User $user, string $module): bool
    {
        $moduleKey = strtolower($module);
        $permissions = $user->getAllPermissions()->pluck('name');

        // Check if user has any permission matching {module}.*
        return $permissions->some(fn ($perm) => str_starts_with($perm, "{$moduleKey}."));
    }
}
