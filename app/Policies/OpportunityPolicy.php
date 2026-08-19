<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 19 (CRM re-audit) fix: same gap and same fix pattern as ContactPolicy — see that
 * class's docblock. crm_opportunities has no company_id column of its own; it carries a
 * tenant_id column instead (added in Chantier 10), populated from the acting user's
 * company_id at creation time (OpportunityController::store()) — so the comparison here is
 * against tenant_id, not company_id, to match the real column this table actually has.
 */
class OpportunityPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'owner_id';

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && parent::update($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && parent::delete($user, $model);
    }

    private function sameTenant(User $user, Model $model): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->tenant_id ?? 0));
    }
}
