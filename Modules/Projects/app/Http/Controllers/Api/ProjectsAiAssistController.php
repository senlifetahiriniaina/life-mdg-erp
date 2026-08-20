<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Projects — AI Assist
 *
 * Contextual AI guidance for Projects module actions.
 */
class ProjectsAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/projects/ai/assist
     *
     * Returns contextual AI guidance for a Projects action.
     *
     * @bodyParam action string required Action key. Example: view_dashboard
     * @bodyParam context array Optional current context data.
     * @bodyParam locale string Locale for the response. Example: fr
     */
    public function assist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'  => ['required', 'string', 'max:128'],
            'context' => ['sometimes', 'array'],
            'locale'  => ['sometimes', 'string', 'max:8'],
        ]);

        // Chantier 19 Lot 2 fix: users.role is a phantom column (real
        // column, never populated by the real registration flow — the
        // same finding already fixed for AI's AiActionAdvisorController
        // and Sales' SalesAiAssistController this session), so every
        // guidance call here silently rendered as the generic 'user' role
        // regardless of who was actually asking. Not a security gap
        // (userRole only tunes the AI prompt tone/cache key, never gates
        // access), but the same real correctness bug — fixed the same way.
        $guidance = $this->assistant->getGuidance(
            module:   'Projects',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
