<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\Recommendation;

class RecommendationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, Recommendation $recommendation): bool
    {
        return ($user->company_id === $recommendation->company_id && $user->hasPermissionTo('analytics.recommendation.view'))
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.create')
            || $user->hasRole('admin');
    }

    public function act(User $user, Recommendation $recommendation): bool
    {
        return $recommendation->status === 'pending'
            && ($user->company_id === $recommendation->company_id && $user->hasPermissionTo('analytics.recommendation.act'))
            || $user->hasRole('admin');
    }

    public function dismiss(User $user, Recommendation $recommendation): bool
    {
        return in_array($recommendation->status, ['pending', 'viewed'])
            && ($user->company_id === $recommendation->company_id && $user->hasPermissionTo('analytics.recommendation.dismiss'))
            || $user->hasRole('admin');
    }
}
