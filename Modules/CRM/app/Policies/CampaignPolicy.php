<?php

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\Campaign;

/**
 * Chantier "CRM tenant-isolation follow-up": view/update/delete/launch/pause all allowed any
 * `admin`-role user (Spatie roles are global, not company-scoped) to act on ANY company's
 * campaign, and the owner-check path never verified the owner and caller share a company
 * either — confirmed via read while investigating the (now-deleted) CampaignOrchestrationService.
 * A real `company_id` column was added to crm_campaigns (previously had none at all) —
 * every ability below now also requires same-company membership, matching the
 * ContactPolicy/CrmAccountPolicy `sameCompany()` convention used elsewhere in this module.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('crm.campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermissionTo('crm.campaigns.view') &&
               $this->sameCompany($user, $campaign) &&
               ($campaign->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('crm.campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermissionTo('crm.campaigns.edit') &&
               $this->sameCompany($user, $campaign) &&
               ($campaign->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasPermissionTo('crm.campaigns.delete') &&
               $this->sameCompany($user, $campaign) &&
               ($campaign->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function launch(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    public function pause(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    private function sameCompany(User $user, Campaign $campaign): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($campaign->company_id ?? 0));
    }
}
