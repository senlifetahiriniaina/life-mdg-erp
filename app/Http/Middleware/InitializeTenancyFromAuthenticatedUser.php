<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Real API requests never call tenancy()->initialize() at all -- there is
 * no domain/subdomain/path-based tenancy middleware wired up (this app
 * uses a shared-DB + tenant_id scoping model, not per-tenant domains), so
 * BelongsToTenant's global scope was a no-op on every live request: any
 * authenticated user could fetch any other tenant's record by ID.
 *
 * Initializes tenancy from the authenticated user's tenant_id, when present.
 * A user with no tenant_id (not yet provisioned) is left in the central
 * context -- existing global scopes/policies still apply.
 */
class InitializeTenancyFromAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId) {
            try {
                tenancy()->initialize($tenantId);
            } catch (\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById) {
                // Stale/invalid tenant_id on the user record -- proceed in
                // the central context rather than hard-failing the request.
            }
        }

        return $next($request);
    }
}
