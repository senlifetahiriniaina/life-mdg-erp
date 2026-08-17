<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\TrustZone;

class TrustZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.zone.view');
    }

    public function view(User $user, TrustZone $trustZone): bool
    {
        if (!$user->hasPermissionTo('security.zone.view')) {
            return false;
        }

        return (string) $user->company_id === (string) $trustZone->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.zone.create');
    }

    public function update(User $user, TrustZone $trustZone): bool
    {
        if (!$user->hasPermissionTo('security.zone.update')) {
            return false;
        }

        return (string) $user->company_id === (string) $trustZone->company_id;
    }

    public function delete(User $user, TrustZone $trustZone): bool
    {
        if (!$user->hasPermissionTo('security.zone.delete')) {
            return false;
        }

        return (string) $user->company_id === (string) $trustZone->company_id;
    }
}
