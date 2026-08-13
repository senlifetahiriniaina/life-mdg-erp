<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\WorkflowDefinition;
use Modules\Core\Models\WorkflowState;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WorkflowService
{
    /**
     * Find the active workflow definition for a given module / resource-type pair.
     */
    public function getDefinition(string $module, string $resourceType): ?WorkflowDefinition
    {
        return WorkflowDefinition::query()
            ->where('module', $module)
            ->where('resource_type', $resourceType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Return the current workflow state for a subject model, or null if none exists.
     */
    public function getState(Model $subject): ?WorkflowState
    {
        return WorkflowState::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->with('definition')
            ->first();
    }

    /**
     * Return the existing state for $subject, or create one at $initialStep.
     */
    public function getOrCreateState(
        Model $subject,
        WorkflowDefinition $definition,
        string $initialStep
    ): WorkflowState {
        $existing = WorkflowState::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->first();

        if ($existing !== null) {
            return $existing->load('definition');
        }

        $state = WorkflowState::create([
            'workflow_definition_id' => $definition->id,
            'subject_type'           => $subject::class,
            'subject_id'             => $subject->getKey(),
            'current_step'           => $initialStep,
            'started_at'             => now(),
        ]);

        return $state->load('definition');
    }

    /**
     * Transition $subject to $toStep if the user's roles permit it.
     *
     * @throws UnprocessableEntityHttpException when the transition is not allowed.
     */
    public function transition(Model $subject, string $toStep, User $user): WorkflowState
    {
        $state = $this->getState($subject);

        if ($state === null) {
            throw new UnprocessableEntityHttpException(
                'No workflow state found for this subject.'
            );
        }

        $definition  = $state->definition;
        $currentStep = $state->current_step;

        if ($definition === null) {
            throw new UnprocessableEntityHttpException(
                'Workflow definition not found for this state.'
            );
        }

        // Collect the user's roles as plain strings.
        $userRoles = $user->getRoleNames()->toArray();

        $allowed = $definition->getAllowedTransitions($currentStep, $userRoles);

        $matchingTransition = null;
        foreach ($allowed as $transition) {
            if (($transition['to'] ?? null) === $toStep) {
                $matchingTransition = $transition;
                break;
            }
        }

        if ($matchingTransition === null) {
            throw new UnprocessableEntityHttpException(
                "Transition from '{$currentStep}' to '{$toStep}' is not allowed for your role(s)."
            );
        }

        $state->current_step = $toStep;

        // Mark as completed when the target step is terminal.
        $targetStepDef = $definition->getStepByKey($toStep);
        if (($targetStepDef['is_terminal'] ?? false) === true) {
            $state->completed_at = now();
        }

        $state->save();

        return $state->fresh()->load('definition');
    }

    /**
     * Return the transitions the user may take from the subject's current step.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableTransitions(Model $subject, User $user): array
    {
        $state = $this->getState($subject);

        if ($state === null || $state->definition === null) {
            return [];
        }

        $userRoles = $user->getRoleNames()->toArray();

        return $state->definition->getAllowedTransitions($state->current_step, $userRoles);
    }
}
