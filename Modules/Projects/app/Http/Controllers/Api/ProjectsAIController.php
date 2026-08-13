<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Services\AI\ProjectsAIService;

/**
 * @group Projects - AI
 *
 * AI-powered project risk and timeline analysis.
 */
class ProjectsAIController extends Controller
{
    public function __construct(private readonly ProjectsAIService $ai) {}

    public function estimateTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task' => 'required|array',
            'similar_tasks' => 'nullable|array',
        ]);

        return response()->json($this->ai->estimateTask($data['task'], $data['similar_tasks'] ?? []));
    }

    public function identifyRisks(Request $request): JsonResponse
    {
        $data = $request->validate(['project' => 'required|array']);

        return response()->json($this->ai->identifyRisks($data['project']));
    }

    public function statusReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project' => 'required|array',
            'completed_tasks' => 'nullable|array',
            'pending_tasks' => 'nullable|array',
        ]);

        return response()->json([
            'report' => $this->ai->generateStatusReport(
                $data['project'],
                $data['completed_tasks'] ?? [],
                $data['pending_tasks'] ?? []
            ),
        ]);
    }

    public function generateTasks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'specification' => ['required', 'string', 'min:10'],
        ]);

        return response()->json($this->ai->generateTasksFromSpec($data['project_id'], $data['specification']));
    }

    public function suggestPrioritization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'tasks' => ['required', 'array', 'min:1'],
        ]);

        return response()->json($this->ai->suggestPrioritization($data['project_id'], $data['tasks']));
    }
}
