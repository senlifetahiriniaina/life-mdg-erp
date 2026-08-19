<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 19 (CRM re-audit) fix: this policy previously inherited BaseErpPolicy's view()
 * unconditionally (return true) with no tenant check at all — any authenticated user of any
 * company could read (and, being an admin/manager of their own unrelated company, even update/
 * delete) any other company's Contact, confirmed empirically via tinker before this fix
 * (documented in CLAUDE.md's Chantier 10 CRM entry as a known, deliberately-deferred gap).
 * Rewired onto the real company_id boundary column (already present on crm_contacts), using
 * the same ?? 0 int-cast sentinel pattern already established by RevenueInsightPolicy and
 * CrmAccountPolicy elsewhere in this module.
 */
class ContactPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'owner_id';

    public function view(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model) && parent::update($user, $model);
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
