<?php

declare(strict_types=1);

namespace Modules\Settings\Policies;

use App\Models\User;
use Modules\Settings\Models\Setting;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('settings.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, Setting $setting): bool
    {
        if ($setting->is_public) {
            return true;
        }

        return $user->hasPermissionTo('settings.view')
            && $this->belongsToTenant($user, $setting);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('settings.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->hasPermissionTo('settings.update')
            && $this->belongsToTenant($user, $setting);
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->hasPermissionTo('settings.delete')
            && $this->belongsToTenant($user, $setting);
    }

    public function viewAll(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function belongsToTenant(User $user, Setting $setting): bool
    {
        // Global settings (tenant_id = null) are only manageable by admins
        if ($setting->tenant_id === null) {
            return $user->hasRole('admin');
        }

        return (int) $setting->tenant_id === (int) $user->company_id
            || $user->hasRole('admin');
    }
}
