<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Services\Automation\AiWorkflowAssistantService;

/**
 * @group Workflow - AI Assistant
 *
 * AI-powered workflow suggestions, failure analysis, validation, and
 * natural-language flow generation.
 *
 * Chantier 32.11: every method here was previously a pure stub returning
 * hardcoded empty data regardless of input (`['suggestions' => []]`, etc.)
 * — despite AiWorkflowAssistantService, a real, fully-written 521-line
 * fallback-first Claude-backed service, already existing with zero
 * consumers anywhere in the app and zero registration in
 * WorkflowServiceProvider. Confirmed via grep this controller had zero
 * routes registered anywhere either, so this was dead code sitting in
 * front of dead code — both wired for real now.
 */
class AiWorkflowController extends Controller
{
    public function __construct(private readonly AiWorkflowAssistantService $assistant) {}

    /**
     * GET /api/v1/workflow/ai/suggest
     *
     * @queryParam locale string Locale for suggestions (fr|en). Example: fr
     */
    public function suggest(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $locale   = $request->input('locale', 'fr');

        return response()->json([
            'suggestions' => $this->assistant->suggestAutomations($tenantId, $locale),
        ]);
    }

    /**
     * GET /api/v1/workflow/ai/analyze-failures
     */
    public function analyzeFailures(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json([
            'analysis' => $this->assistant->analyzeFailures($tenantId),
        ]);
    }

    /**
     * POST /api/v1/workflow/ai/validate
     *
     * @bodyParam nodes array Flow nodes.
     * @bodyParam connections array Flow connections.
     * @bodyParam trigger_type string Trigger type.
     */
    public function validateFlow(Request $request): JsonResponse
    {
        $flowDefinition = $request->validate([
            'nodes'          => 'sometimes|array',
            'connections'    => 'sometimes|array',
            'trigger_type'   => 'sometimes|string',
            'name'           => 'sometimes|string',
            'description'    => 'sometimes|nullable|string',
        ]);

        $result = $this->assistant->validateFlow($flowDefinition);

        return response()->json($result);
    }

    /**
     * POST /api/v1/workflow/ai/generate
     *
     * @bodyParam description string required Natural-language description of the desired automation.
     * @bodyParam locale string Locale (fr|en). Example: fr
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:2000',
            'locale'      => 'sometimes|string|in:fr,en',
        ]);

        $flow = $this->assistant->generateFlowFromDescription(
            $validated['description'],
            $validated['locale'] ?? 'fr',
        );

        return response()->json(['flow' => $flow]);
    }

    /**
     * GET /api/v1/workflow/ai/flows/{flowId}/summary
     *
     * @queryParam period string Period to summarize (day|week|month). Example: week
     */
    public function summary(Request $request, string $flowId): JsonResponse
    {
        $period = $request->input('period', 'week');

        $summary = $this->assistant->summarizeExecutions((int) $flowId, $period);

        return response()->json(['summary' => $summary, 'flow_id' => $flowId]);
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }
}
