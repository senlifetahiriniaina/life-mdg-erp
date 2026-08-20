<?php

declare(strict_types=1);

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group API — AI Assist
 *
 * Contextual AI guidance for API module actions.
 */
class APIAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/api/ai/assist
     *
     * Returns contextual AI guidance for a API action.
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
            module:   'API',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 Lot 3: the phantom users.role column (real DB
            // column, never in User::$fillable, never populated) was always
            // null here, silently defeating the AI-assist tone/depth logic
            // for every user of every role — same bug class already fixed
            // in Sales/HR/Payroll/Calendar/Projects/Setup/Timesheets/
            // Workflow's own AI-assist controllers elsewhere this session.
            userRole: $request->user()?->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
