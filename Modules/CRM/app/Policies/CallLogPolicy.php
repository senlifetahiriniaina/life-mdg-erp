<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\CallLog;

/**
 * Chantier 32.15 (CRM 14-layer audit): this policy existed but was never registered with the
 * Gate and never called from VoipController — any authenticated CRM-module user of any
 * company could list/read every other company's call logs (phone numbers, notes, recording
 * links) via callLogs()/showCallLog(), confirmed empirically before this fix. crm_call_logs
 * already carried a real `tenant_id` column (from the original catch-all scaffold), just
 * never populated by VoipService::initiateCall()/recordCallLog() nor filtered on anywhere —
 * both fixed alongside this policy, using the same `?? 0` sentinel convention already
 * established by RevenueInsightPolicy/CrmAccountPolicy elsewhere in this module.
 */
class CallLogPolicy
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
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        if (! $this->sameCompany($user, $model)) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        /** @var CallLog $model */
        return (int) $model->user_id === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model) && $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    private function sameCompany(User $user, Model $model): bool
    {
        /** @var CallLog $model */
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->tenant_id ?? 0));
    }
}
