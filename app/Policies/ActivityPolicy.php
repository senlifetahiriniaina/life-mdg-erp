<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier "CRM tenant-isolation follow-up": ActivityController had zero `authorize()` calls
 * and crm_activities had no company/tenant column at all — any authenticated CRM-module user
 * could list/view/update/delete any other company's logged calls/emails/meetings/notes.
 * Rewired onto a new, additive `company_id` column, matching the ContactPolicy/CrmAccountPolicy
 * `sameCompany()` convention used elsewhere in this module.
 */
class ActivityPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'user_id';

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
