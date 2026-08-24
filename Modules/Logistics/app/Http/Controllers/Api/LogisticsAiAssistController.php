<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Logistics — AI Assist
 *
 * Contextual AI guidance for Logistics module actions.
 */
class LogisticsAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/logistics/ai/assist
     *
     * Returns contextual AI guidance for a Logistics action.
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

        // Chantier 32.23: `users.role` is the well-documented phantom column
        // (real, migrated, never populated by any real registration path) —
        // this read always resolved to the literal string 'user' regardless
        // of the real actor's role, the same bug class already fixed for
        // ~15 other modules' *AiAssistController this session but missed
        // here. Fixed to the established Spatie-role pattern.
        $guidance = $this->assistant->getGuidance(
            module:   'Logistics',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
