<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group CRM — AI Assist
 *
 * Contextual AI guidance for CRM module actions.
 */
class CRMAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/crm/ai/assist
     *
     * Returns contextual AI guidance for a CRM action.
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

        // Chantier 38.3: $request->user()->role read the well-documented phantom
        // `users.role` column (real, migrated, never populated by any real registration
        // path) — the same bug class already fixed on Sales/Strategy/HR's own dedicated
        // AI-assist controllers, missed here. Fixed to the real Spatie role assignment.
        $guidance = $this->assistant->getGuidance(
            module:   'CRM',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
