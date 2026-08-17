<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\ServiceIdentity;

class ServiceIdentityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.identity.view');
    }

    public function view(User $user, ServiceIdentity $serviceIdentity): bool
    {
        if (!$user->hasPermissionTo('security.identity.view')) {
            return false;
        }

        return (string) $user->company_id === (string) $serviceIdentity->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.identity.create');
    }

    public function update(User $user, ServiceIdentity $serviceIdentity): bool
    {
        if (!$user->hasPermissionTo('security.identity.update')) {
            return false;
        }

        return (string) $user->company_id === (string) $serviceIdentity->company_id;
    }

    public function rotate(User $user, ServiceIdentity $serviceIdentity): bool
    {
        if (!$user->hasPermissionTo('security.identity.rotate')) {
            return false;
        }

        return (string) $user->company_id === (string) $serviceIdentity->company_id;
    }

    public function delete(User $user, ServiceIdentity $serviceIdentity): bool
    {
        if (!$user->hasPermissionTo('security.identity.delete')) {
            return false;
        }

        return (string) $user->company_id === (string) $serviceIdentity->company_id;
    }
}
