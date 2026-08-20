<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Security — AI Assist
 *
 * Contextual AI guidance for Security module actions.
 */
class SecurityAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/security/ai/assist
     *
     * Returns contextual AI guidance for a Security action.
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
            module:   'Security',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 Lot 3: same phantom `users.role` bug as
            // Modules\AI\Http\Controllers\Api\AiAssistantController::assist()
            // (fixed alongside this in the same pass) — `role` is never
            // populated by the real registration flow, so every real caller
            // was reported to the AI provider as a generic 'user' regardless
            // of their real Spatie role.
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
