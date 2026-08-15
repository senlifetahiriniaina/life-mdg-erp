<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Core\Models\WorkflowDefinition;
use Modules\Core\Models\WorkflowState;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @group Core - Workflow Engine
 *
 * Manage workflow definitions and drive subjects through state transitions.
 */
class WorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all active workflow definitions.
     *
     * GET /api/v1/core/workflows
     */
    public function index(): JsonResponse
    {
        $definitions = WorkflowDefinition::query()
            ->orderBy('module')
            ->orderBy('resource_type')
            ->get();

        return response()->json(['data' => $definitions]);
    }

    /**
     * Show the definition for a specific module + resource type.
     *
     * GET /api/v1/core/workflows/{module}/{type}
     */
    public function show(string $module, string $type): JsonResponse
    {
        $definition = $this->findDefinitionOrFail($module, $type);

        return response()->json($definition);
    }

    /**
     * Apply a transition to a subject.
     *
     * POST /api/v1/core/workflows/{module}/{type}/{subjectId}/transition
     */
    public function transition(Request $request, string $module, string $type, int $subjectId): JsonResponse
    {
        $request->validate([
            'to_step' => ['required', 'string'],
        ]);

        $definition = $this->findDefinitionOrFail($module, $type);

        // Resolve the subject via its workflow state.
        $state = WorkflowState::query()
            ->where('workflow_definition_id', $definition->id)
            ->where('subject_id', $subjectId)
            ->first();

        if ($state === null) {
            throw new NotFoundHttpException(
                "No workflow state found for subject ID {$subjectId}."
            );
        }

        $user = $request->user();
        $userRoles = $user->getRoleNames()->toArray();

        $allowed = $definition->getAllowedTransitions($state->current_step, $userRoles);

        $toStep = $request->input('to_step');
        $matchingTransition = null;
        foreach ($allowed as $transition) {
            if (($transition['to'] ?? null) === $toStep) {
                $matchingTransition = $transition;
                break;
            }
        }

        if ($matchingTransition === null) {
            throw new UnprocessableEntityHttpException(
                "Transition from '{$state->current_step}' to '{$toStep}' is not allowed for your role(s)."
            );
        }

        $state->current_step = $toStep;

        $targetStepDef = $definition->getStepByKey($toStep);
        if (($targetStepDef['is_terminal'] ?? false) === true) {
            $state->completed_at = now();
        }

        $state->save();

        return response()->json($state->fresh()->load('definition'));
    }

    /**
     * Get the current workflow state for a subject.
     *
     * GET /api/v1/core/workflows/{module}/{type}/{subjectId}/state
     */
    public function state(string $module, string $type, int $subjectId): JsonResponse
    {
        $definition = $this->findDefinitionOrFail($module, $type);

        $state = WorkflowState::query()
            ->where('workflow_definition_id', $definition->id)
            ->where('subject_id', $subjectId)
            ->with('definition')
            ->first();

        if ($state === null) {
            throw new NotFoundHttpException(
                "No workflow state found for subject ID {$subjectId}."
            );
        }

        return response()->json([
            'state' => $state,
            'current_step' => $state->getCurrentStep(),
            'is_completed' => $state->isCompleted(),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function findDefinitionOrFail(string $module, string $type): WorkflowDefinition
    {
        $definition = WorkflowDefinition::query()
            ->where('module', $module)
            ->where('resource_type', $type)
            ->first();

        if ($definition === null) {
            throw new NotFoundHttpException(
                "No workflow definition found for {$module}/{$type}."
            );
        }

        return $definition;
    }
}
