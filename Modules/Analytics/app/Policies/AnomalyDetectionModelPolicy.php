<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\AnomalyDetectionModel;

class AnomalyDetectionModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.anomaly.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, AnomalyDetectionModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.anomaly.view'))
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.anomaly.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, AnomalyDetectionModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.anomaly.update'))
            || $user->hasRole('admin');
    }

    public function configure(User $user, AnomalyDetectionModel $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->hasPermissionTo('analytics.anomaly.configure') || $user->hasRole('admin'));
    }

    public function delete(User $user, AnomalyDetectionModel $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->hasPermissionTo('analytics.anomaly.delete') || $user->hasRole('admin'));
    }
}
