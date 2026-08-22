<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Chantier 32.22: this module had zero company/tenant scoping anywhere on
 * its 6 highest-value resources (products, categories, warehouses,
 * suppliers, stock movements, purchase orders) — confirmed empirically
 * with 2 real companies and a real HTTP request that any user with an
 * Inventory role could read/edit/delete any other company's data.
 *
 * Matches the `$user->company_id` (not the phantom `users.tenant_id`, and
 * not `Product`'s own `BelongsToTenant`/stancl-tenancy mechanism, which is
 * a no-op outside a real tenant-domain request) convention already
 * established repeatedly across this app this session — same shape as
 * `Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany`. A
 * record/user with no real `company_id` yet (pre-chantier data, a
 * not-yet-provisioned user) shares the same untagged bucket rather than
 * being always denied, so this migration doesn't lock out legitimate
 * existing test/demo data.
 */
trait ScopesToCompany
{
    protected function companyId(Request $request): ?int
    {
        return $request->user()?->company_id;
    }

    /** Scope a query builder to the caller's own company bucket. */
    protected function scopeToCompany(Builder $query, Request $request): Builder
    {
        return $query->where('company_id', $this->companyId($request));
    }

    /**
     * Aborts with 404 (not 403 — matching this app's established
     * not-your-tenant-data-doesn't-exist-to-you convention) if the given
     * record belongs to a different company than the caller.
     */
    protected function assertSameCompany(Request $request, Model $model): void
    {
        abort_unless($model->company_id === $this->companyId($request), 404);
    }
}
