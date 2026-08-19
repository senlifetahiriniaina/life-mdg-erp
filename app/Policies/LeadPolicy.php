<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 19 (CRM re-audit) fix: same gap and same fix pattern as ContactPolicy — see that
 * class's docblock. crm_leads already carries a real company_id column.
 */
class LeadPolicy extends BaseErpPolicy
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
