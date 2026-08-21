<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Account policy.
 *
 * Accounts are shared team resources: any same-company user may view and update them.
 * Deletion stays restricted to admins/managers/owner via the base policy, additionally
 * gated by same-company membership below.
 *
 * Chantier 19 (CRM re-audit) fix: this policy previously compared $user->tenant_id against
 * $model->tenant_id — the phantom `users.tenant_id` column (real, migrated, never populated
 * by any real registration path, documented repeatedly throughout CLAUDE.md) against a
 * tenant_id column that never even existed on crm_accounts at all. Both sides always resolved
 * to '', so the "cross-tenant access is denied" comment above was never actually true — any
 * authenticated user of any company could view AND update any other company's Account,
 * confirmed empirically via tinker before this fix. Rewired onto the real company_id boundary
 * column (new, additive migration on crm_accounts), using the same ?? 0 int-cast sentinel
 * pattern already established by RevenueInsightPolicy::sameCompany() elsewhere in this module.
 */
class CrmAccountPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'owner_id';

    public function view(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        // Chantier 19's own docblock above states accounts are shared team resources
        // that "any same-company user may view and update" — but this method also
        // AND'd in parent::update() (isAdminOrOwner), which restricts non-admin/manager
        // users to the record's owner_id. AccountFactory's owner_id defaults to a random
        // unrelated User::factory(), so any non-owner same-company employee got a 403 on
        // every real update — confirmed via a real HTTP request in the root test suite,
        // contradicting both this class's own doc comment and view()'s (company-only)
        // behavior. Restricted to the same-company check only, matching the stated intent.
        return $this->sameCompany($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model) && parent::delete($user, $model);
    }

    private function sameCompany(User $user, Model $model): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->company_id ?? 0));
    }
}
