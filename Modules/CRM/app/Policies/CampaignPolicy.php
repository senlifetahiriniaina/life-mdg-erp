<?php

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\Campaign;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('crm.campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermissionTo('crm.campaigns.view') &&
               ($campaign->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('crm.campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermissionTo('crm.campaigns.edit') &&
               ($campaign->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasPermissionTo('crm.campaigns.delete') &&
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
}
