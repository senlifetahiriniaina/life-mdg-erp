<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\RecommendationModel;

class RecommendationModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, RecommendationModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.recommendation.view'))
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, RecommendationModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.recommendation.update'))
            || $user->hasRole('admin');
    }

    public function train(User $user, RecommendationModel $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->hasPermissionTo('analytics.recommendation.train') || $user->hasRole('admin'));
    }

    public function delete(User $user, RecommendationModel $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->hasPermissionTo('analytics.recommendation.delete') || $user->hasRole('admin'));
    }
}
