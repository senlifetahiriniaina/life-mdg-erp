<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Setup — AI Assist
 *
 * Contextual AI guidance for Setup module actions.
 */
class SetupAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/setup/ai/assist
     *
     * Returns contextual AI guidance for a Setup action.
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
            module:   'Setup',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 Lot 3: was `$request->user()?->role ?? 'user'` — the phantom
            // `users.role` column (never populated by any real registration path,
            // same bug class already fixed for HR/Payroll/Projects/Sales/Timesheets'
            // AI-assist controllers) always fell through to the 'user' default,
            // silently defeating the AI guidance's role-based tone/depth for every
            // caller including admins. Fixed to read the real Spatie role.
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
