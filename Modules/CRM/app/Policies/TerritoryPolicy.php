<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\Territory;

/**
 * Chantier 32.15 (CRM 14-layer audit): TerritoryController/TerritoryService/
 * TerritoryForecastService had zero authorize()/tenant-scoping calls anywhere across the
 * whole subsystem (list/create/show/update/delete/forecast/rebalance/teamQuotas/coverage/
 * assignOpportunity) — any authenticated CRM-module user of any company could read and
 * mutate any other company's sales territories (region, quota, assigned rep), confirmed
 * empirically before this fix. crm_territories never had a tenant/company column of any
 * kind — a new, additive `company_id` column was added alongside this new policy.
 */
class TerritoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Territory $territory): bool
    {
        return $this->sameCompany($user, $territory);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Territory $territory): bool
    {
        return $this->sameCompany($user, $territory);
    }

    public function delete(User $user, Territory $territory): bool
    {
        return $this->sameCompany($user, $territory) && $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    private function sameCompany(User $user, Territory $territory): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($territory->company_id ?? 0));
    }
}
