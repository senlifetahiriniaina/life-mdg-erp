<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier "CRM tenant-isolation follow-up": PipelineController had zero `authorize()` calls
 * and crm_pipelines had no company/tenant column at all — any authenticated CRM-module user
 * could view/edit/delete any other company's pipeline stage configuration. Rewired onto a new,
 * additive `company_id` column, matching the ContactPolicy/CrmAccountPolicy `sameCompany()`
 * convention used elsewhere in this module. Pipelines have no natural owner (they are shared
 * team configuration, same category as Account), so there is no $ownerColumn — mutation is
 * gated purely on same-company membership plus BaseErpPolicy's own admin/manager fallback.
 */
class PipelinePolicy extends BaseErpPolicy
{
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
