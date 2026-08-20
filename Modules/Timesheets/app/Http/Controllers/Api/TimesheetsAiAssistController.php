<?php

declare(strict_types=1);

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Timesheets — AI Assist
 *
 * Contextual AI guidance for Timesheets module actions.
 */
class TimesheetsAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/timesheets/ai/assist
     *
     * Returns contextual AI guidance for a Timesheets action.
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

        $guidance = $this->assistant->getGuidance(
            module:   'Timesheets',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 (Lot 2): users.role is the well-documented
            // phantom column (real, migrated, never populated by any real
            // registration path) — this always resolved to the 'user'
            // fallback regardless of the caller's real Spatie role,
            // silently defeating the AI-assist tone/depth logic on every
            // call. Same fix pattern already used by SalesAiAssistController/
            // AiActionAdvisorController.
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
