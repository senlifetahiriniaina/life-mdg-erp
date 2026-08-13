<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\ApprovalDecision;
use Modules\Core\Models\ApprovalInstance;
use Modules\Core\Models\ApprovalWorkflow;

class ApprovalService
{
    /**
     * Find the active workflow for a given module + resource type.
     */
    public function getWorkflowForResource(string $module, string $resourceType): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('module', $module)
            ->where('resource_type', $resourceType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Start a new approval instance for the given subject model.
     */
    public function startApproval(Model $subject, ApprovalWorkflow $workflow, User $initiator): ApprovalInstance
    {
        $instance = ApprovalInstance::create([
            'workflow_id'  => $workflow->id,
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->getKey(),
            'current_step' => 1,
            'status'       => 'pending',
            'initiated_by' => $initiator->id,
        ]);

        $this->notifyCurrentStepApprovers($instance);

        return $instance;
    }

    /**
     * Record a decision for the current step of an instance.
     */
    public function submitDecision(
        ApprovalInstance $instance,
        User $approver,
        string $decision,
        ?string $comment
    ): ApprovalDecision {
        $decisionRecord = ApprovalDecision::create([
            'instance_id' => $instance->id,
            'step_order'  => $instance->current_step,
            'approver_id' => $approver->id,
            'decision'    => $decision,
            'comment'     => $comment,
            'decided_at'  => now(),
        ]);

        if ($decision === 'rejected') {
            $instance->update([
                'status'       => 'rejected',
                'completed_at' => now(),
            ]);
        } else {
            $this->advanceToNextStep($instance);
        }

        return $decisionRecord;
    }

    /**
     * Determine whether the given user can approve the current step.
     */
    public function canApprove(ApprovalInstance $instance, User $user): bool
    {
        if ($instance->status !== 'pending') {
            return false;
        }

        $instance->loadMissing('workflow');
        $steps   = $instance->workflow->steps ?? [];
        $stepIdx = $instance->current_step - 1;

        if (!isset($steps[$stepIdx])) {
            return false;
        }

        return $this->userMatchesStep($user, $steps[$stepIdx]);
    }

    public function canApproveWithRoles(ApprovalInstance $instance, User $user, array $userRoleIds): bool
    {
        return $this->canApprove($instance, $user);
    }

    /**
     * Move the instance to the next step, or mark it approved if all steps are done.
     */
    public function advanceToNextStep(ApprovalInstance $instance): void
    {
        $instance->refresh();
        $instance->loadMissing('workflow');
        $steps    = $instance->workflow->steps ?? [];
        $nextStep = $instance->current_step + 1;

        if ($nextStep > count($steps)) {
            $instance->update([
                'status'       => 'approved',
                'completed_at' => now(),
            ]);
        } else {
            $instance->update(['current_step' => $nextStep]);
            $this->notifyCurrentStepApprovers($instance);
        }
    }

    /**
     * Cancel an in-progress approval instance.
     */
    public function cancelApproval(ApprovalInstance $instance, User $user): void
    {
        if ($instance->isCompleted()) {
            return;
        }

        $instance->update([
            'status'       => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function userMatchesStep(User $user, array $step): bool
    {
        $approverType  = $step['approver_type'] ?? null;
        $approverValue = $step['approver_value'] ?? null;

        if ($approverType === 'user') {
            return (int) $approverValue === $user->id;
        }

        if ($approverType === 'role' && $approverValue !== null) {
            return $user->hasRole((string) $approverValue);
        }

        if ($approverType === 'department' && $approverValue !== null) {
            // Fallback: check a 'department' attribute on the user if it exists
            return isset($user->department) && $user->department === $approverValue;
        }

        return false;
    }

    private function notifyCurrentStepApprovers(ApprovalInstance $instance): void
    {
        $instance->loadMissing('workflow');
        $steps   = $instance->workflow->steps ?? [];
        $stepIdx = $instance->current_step - 1;

        if (!isset($steps[$stepIdx])) {
            return;
        }

        $step          = $steps[$stepIdx];
        $approverType  = $step['approver_type'] ?? null;
        $approverValue = $step['approver_value'] ?? null;

        if ($approverType === 'user' && $approverValue !== null) {
            $user = User::find((int) $approverValue);
            if ($user) {
                $this->sendApprovalEmail($user, $instance);
            }

            return;
        }

        if ($approverType === 'role' && $approverValue !== null) {
            try {
                $users = User::role((string) $approverValue)->get();
                foreach ($users as $user) {
                    $this->sendApprovalEmail($user, $instance);
                }
            } catch (\Exception) {
                // Role may not exist in all environments; notification is best-effort
            }
        }
    }

    private function sendApprovalEmail(User $user, ApprovalInstance $instance): void
    {
        try {
            Mail::raw(
                "Dear {$user->name},\n\nA record requires your approval.\n\nWorkflow: {$instance->workflow->name}\nSubject: {$instance->subject_type} #{$instance->subject_id}",
                function ($message) use ($user) {
                    $message->to($user->email, $user->name)
                        ->subject('Approval Required');
                }
            );
        } catch (\Exception) {
            // Mail failure must not break the approval flow
        }
    }
}
