<?php

declare(strict_types=1);

namespace Modules\Reporting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Reporting — AI Assist
 *
 * Contextual AI guidance for Reporting module actions.
 */
class ReportingAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/reporting/ai/assist
     *
     * Returns contextual AI guidance for a Reporting action.
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
            module:   'Reporting',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 (Lot 5): $request->user()?->role read the phantom
            // users.role column (real DB column, never in User::$fillable, never
            // populated by any real registration path) instead of the real Spatie
            // role — the same bug class already fixed repeatedly elsewhere this
            // session (AI, Security, AuditLog, Achats, Sales, Calendar, ...).
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
