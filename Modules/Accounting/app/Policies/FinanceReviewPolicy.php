<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\FinanceReview;

/** Chantier 26 (volet D). */
class FinanceReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.financereview.view-any');
    }

    public function view(User $user, FinanceReview $review): bool
    {
        return $user->hasPermissionTo('accounting.financereview.view')
            && $this->belongsToCompany($user, $review->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.financereview.create');
    }

    public function update(User $user, FinanceReview $review): bool
    {
        return $user->hasPermissionTo('accounting.financereview.update')
            && $this->belongsToCompany($user, $review->company_id);
    }

    public function delete(User $user, FinanceReview $review): bool
    {
        return $user->hasPermissionTo('accounting.financereview.delete')
            && $this->belongsToCompany($user, $review->company_id);
    }

    private function belongsToCompany(User $user, ?int $companyId): bool
    {
        return $companyId === null || $user->company_id === $companyId || $user->hasRole('admin');
    }
}
