<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group BI — AI Assist
 *
 * Contextual AI guidance for BI module actions.
 */
class BIAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/bi/ai/assist
     *
     * Returns contextual AI guidance for a BI action.
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
            module:   'BI',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Bug fix (Chantier 19 Lot 5): users.role is a phantom column, never
            // populated by the real registration flow — this always silently
            // fell back to the generic 'user' tone regardless of the caller's
            // real role. Fixed to the real Spatie role name, matching the fix
            // already applied to API/Achats/Calendar/HR/etc.'s equivalent
            // controllers earlier this session.
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
