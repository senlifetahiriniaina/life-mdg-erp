<?php

declare(strict_types=1);

namespace Modules\AI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AI\Services\AiContextualAssistantService;

class AiAssistantController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/ai/assist
     *
     * Returns contextual AI guidance for a given module + action.
     *
     * Body (JSON):
     *   - module   string  required  e.g. "CRM"
     *   - action   string  required  e.g. "create_contact"
     *   - context  array   optional  current data context
     *   - locale   string  optional  default "fr"
     */
    public function assist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module'  => ['required', 'string', 'max:64'],
            'action'  => ['required', 'string', 'max:128'],
            'context' => ['sometimes', 'array'],
            'locale'  => ['sometimes', 'string', 'max:8'],
        ]);

        $locale   = $validated['locale']  ?? 'fr';
        $context  = $validated['context'] ?? [];
        $userRole = $request->user()?->role ?? 'user';

        $guidance = $this->assistant->getGuidance(
            module:   $validated['module'],
            action:   $validated['action'],
            context:  $context,
            locale:   $locale,
            userRole: $userRole,
        );

        return response()->json($guidance);
    }

    /**
     * GET /api/v1/ai/assist/modules
     *
     * Returns the list of supported modules and their actions.
     * Useful for the frontend to pre-fetch and warm the cache.
     */
    public function modules(): JsonResponse
    {
        return response()->json([
            'modules' => $this->assistant->supportedModules(),
        ]);
    }
}
