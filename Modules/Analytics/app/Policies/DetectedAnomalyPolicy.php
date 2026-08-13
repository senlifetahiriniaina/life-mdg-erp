<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\DetectedAnomaly;

class DetectedAnomalyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.anomaly.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, DetectedAnomaly $anomaly): bool
    {
        return ($user->company_id === $anomaly->company_id && $user->hasPermissionTo('analytics.anomaly.view'))
            || $user->hasRole('admin');
    }

    public function investigate(User $user, DetectedAnomaly $anomaly): bool
    {
        return $anomaly->status === 'new'
            && ($user->company_id === $anomaly->company_id && $user->hasPermissionTo('analytics.anomaly.investigate'))
            || $user->hasRole('admin');
    }

    public function resolve(User $user, DetectedAnomaly $anomaly): bool
    {
        return in_array($anomaly->status, ['new', 'investigating'])
            && ($user->company_id === $anomaly->company_id && $user->hasPermissionTo('analytics.anomaly.resolve'))
            || $user->hasRole('admin');
    }

    public function dismiss(User $user, DetectedAnomaly $anomaly): bool
    {
        return $user->company_id === $anomaly->company_id
            && ($user->hasPermissionTo('analytics.anomaly.dismiss') || $user->hasRole('admin'));
    }
}
