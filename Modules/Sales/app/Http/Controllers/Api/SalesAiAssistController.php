<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Sales — AI Assist
 *
 * Contextual AI guidance for sales order and quotation actions.
 */
class SalesAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/sales/ai/assist
     *
     * Returns contextual AI guidance for a sales action.
     *
     * @bodyParam action string required Action key (e.g. create_order, confirm_order, create_quotation, convert_quote). Example: create_order
     * @bodyParam context array Optional current context data. Example: {"currency": "XOF", "lines_count": 3}
     * @bodyParam locale string Locale for the response. Example: fr
     */
    public function assist(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.view'), 403);

        $validated = $request->validate([
            'action'  => ['required', 'string', 'max:128'],
            'context' => ['sometimes', 'array'],
            'locale'  => ['sometimes', 'string', 'max:8'],
        ]);

        $guidance = $this->assistant->getGuidance(
            module:   'Sales',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            userRole: $request->user()->role ?? 'user',
        );

        return response()->json($guidance);
    }
}
