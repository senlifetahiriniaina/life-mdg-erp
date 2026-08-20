<?php

declare(strict_types=1);

namespace Modules\AI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
        // Chantier 19 Lot 3: `users.role` is the well-documented phantom
        // column (real DB column, never in `User::$fillable`, never
        // populated by the real registration flow — real RBAC is Spatie
        // roles) — this was always null, so every real call to this
        // module's own primary AI-guidance endpoint silently told Claude
        // (and the static-fallback branch) every caller was a generic
        // 'user', regardless of their real role, defeating the "influences
        // depth/tone of guidance" contract this controller's own docblock
        // and CLAUDE.md's AI Assisted First section both document. Fixed to
        // the real Spatie role, matching the identical fix already applied
        // to AiActionAdvisorController/SalesAiAssistController elsewhere in
        // this session.
        $userRole = $request->user()?->getRoleNames()->first() ?? 'user';

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
