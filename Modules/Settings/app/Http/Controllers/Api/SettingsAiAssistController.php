<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Settings — AI Assist
 *
 * Contextual AI guidance for Settings module actions.
 */
class SettingsAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/settings/ai/assist
     *
     * Returns contextual AI guidance for a Settings action.
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
            module:   'Settings',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 Lot 3: phantom users.role column fix (see
            // APIAiAssistController's identical fix for the full rationale).
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
