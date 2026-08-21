<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\EmailSequence;

/**
 * Chantier 32.15 (CRM 14-layer audit): this policy existed but was never registered with the
 * Gate, and EmailSequenceController had zero authorize()/tenant-scoping calls of any kind
 * across every one of its ~15 endpoints — any authenticated CRM-module user of any company
 * could list/read/update/delete/activate/pause/enroll-into every other company's email
 * sequences, confirmed empirically before this fix. crm_email_sequences already carried a
 * real `tenant_id` column, just never populated/filtered — fixed alongside this policy.
 */
class EmailSequencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'sales-rep']);
    }

    public function update(User $user, Model $model): bool
    {
        if (! $this->sameCompany($user, $model)) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        /** @var EmailSequence $model */
        return (int) $model->created_by === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        if (! $this->sameCompany($user, $model)) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        /** @var EmailSequence $model */
        return (int) $model->created_by === $user->id;
    }

    private function sameCompany(User $user, Model $model): bool
    {
        /** @var EmailSequence $model */
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->tenant_id ?? 0));
    }
}
