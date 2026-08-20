<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group HR — AI Assist
 *
 * Contextual AI guidance for HR module actions.
 */
class HRAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/hr/ai/assist
     *
     * Returns contextual AI guidance for a HR action.
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

        // Chantier 19 (HR): $user->role reads the well-documented phantom
        // users.role column — a real DB column, in $fillable, but never
        // actually written by any real registration/seeding path in this
        // app (DemoSeeder only ever calls ->syncRoles() against Spatie, the
        // same pattern already fixed for this exact bug class in AI's
        // AiActionAdvisorController and Sales' SalesAiAssistController) —
        // so userRole was always the static 'user' fallback regardless of
        // the caller's real role, silently defeating the AI guidance's
        // role-aware tone/depth. Fixed to the real Spatie role, matching
        // LeaveRequestController::approve()'s existing getRoleNames() usage.
        $guidance = $this->assistant->getGuidance(
            module:   'HR',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
