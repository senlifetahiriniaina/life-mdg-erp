<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\ABTestRun;

class ABTestRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ab_test.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, ABTestRun $test): bool
    {
        return ($user->company_id === $test->company_id && $user->hasPermissionTo('analytics.ab_test.view'))
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ab_test.create')
            || $user->hasRole('admin');
    }

    public function start(User $user, ABTestRun $test): bool
    {
        return $test->status === 'planned'
            && ($user->company_id === $test->company_id && $user->hasPermissionTo('analytics.ab_test.start'))
            || $user->hasRole('admin');
    }

    public function complete(User $user, ABTestRun $test): bool
    {
        return $test->status === 'running'
            && ($user->company_id === $test->company_id && $user->hasPermissionTo('analytics.ab_test.complete'))
            || $user->hasRole('admin');
    }

    public function deploy(User $user, ABTestRun $test): bool
    {
        return $test->status === 'completed' && $test->winner
            && ($user->company_id === $test->company_id && $user->hasPermissionTo('analytics.ab_test.deploy'))
            || $user->hasRole('admin');
    }

    public function delete(User $user, ABTestRun $test): bool
    {
        return $test->status === 'planned'
            && ($user->company_id === $test->company_id && $user->hasPermissionTo('analytics.ab_test.delete'))
            || $user->hasRole('admin');
    }
}
