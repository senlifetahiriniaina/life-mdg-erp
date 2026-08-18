<?php

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\CallRecording;

/**
 * Chantier 10 fix: CallRecordingController had zero authorize() calls and zero tenant
 * filtering despite CallRecording already carrying a real company_id column — any
 * authenticated user of any company could read (and queue AI summarization of) another
 * company's call recordings/transcripts.
 */
class CallRecordingPolicy
{
    public function view(User $user, CallRecording $recording): bool
    {
        return $this->can($user, 'crm.call-recording.view')
            && $this->sameCompany($user, $recording);
    }

    public function summarize(User $user, CallRecording $recording): bool
    {
        return $this->can($user, 'crm.call-recording.summarize')
            && $this->sameCompany($user, $recording);
    }

    private function sameCompany(User $user, CallRecording $recording): bool
    {
        return $user->hasRole('admin')
            || ((int) ($user->company_id ?? 0)) === ((int) ($recording->company_id ?? 0));
    }

    /**
     * See RevenueInsightPolicy::can() — same "crm.call-recording.* isn't seeded yet, fail
     * closed rather than let Spatie's PermissionDoesNotExist 500 every request" reasoning.
     */
    private function can(User $user, string $permission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }
}
