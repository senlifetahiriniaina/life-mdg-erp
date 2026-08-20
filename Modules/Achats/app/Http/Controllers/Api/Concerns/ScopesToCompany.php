<?php

declare(strict_types=1);

namespace Modules\Achats\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Chantier 19 (Lot 3 — Achats): this module had zero company/tenant
 * scoping anywhere — confirmed empirically with 2 real companies that any
 * authenticated user with an Achats role could read/edit/delete any other
 * company's suppliers, purchase orders, RFQs, receipts, and quotes.
 *
 * Matches the `$user->company_id` (not the phantom `users.tenant_id`)
 * convention already established repeatedly across this app this session,
 * and the same null==null "untagged bucket" comparison already used by
 * `Modules\CRM\Http\Controllers\Api\ContactController` — a record/user
 * with no real company_id yet (pre-chantier data, a not-yet-provisioned
 * user) are treated as the same bucket rather than always denied, so this
 * migration doesn't lock legitimate existing test/demo data out.
 */
trait ScopesToCompany
{
    protected function companyId(Request $request): ?int
    {
        return $request->user()?->company_id;
    }

    /**
     * Aborts with 404 (not 403 — matching this app's established
     * not-your-tenant-data-doesn't-exist-to-you convention, e.g. Projects'
     * ScopesToProjectCompany) if the given record belongs to a different
     * company than the caller.
     */
    protected function assertSameCompany(Request $request, Model $model): void
    {
        abort_unless($model->company_id === $this->companyId($request), 404);
    }
}
