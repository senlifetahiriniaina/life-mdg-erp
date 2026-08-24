<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group AuditLog — AI Assist
 *
 * Contextual AI guidance for audit log browsing and compliance actions.
 */
class AuditLogAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/audit-log/ai/assist
     *
     * Returns contextual AI guidance for audit log actions.
     *
     * Chantier 32.4: the example action keys below previously listed
     * `view_logs`/`anomaly_detection`/`compliance_report`, none of which
     * are registered in AiContextualAssistantService::supportedModules()
     * (the real, registered `AuditLog` actions are `view_audit_log`,
     * `export_audit`, `filter_events`) — any of those 3 fictional names
     * silently fell through to an empty guidance shell (enabled:false,
     * every field blank) rather than a real fallback, invisible to
     * AuditLogRoutesTest.php's own coverage since that test mocks
     * AiContextualAssistantService entirely. Corrected to the real,
     * registered actions.
     *
     * @bodyParam action string required Action key (view_audit_log, export_audit, or filter_events). Example: view_audit_log
     * @bodyParam context array Optional current context data. Example: {"module": "Accounting", "event_type": "deleted"}
     * @bodyParam locale string Locale for the response. Example: fr
     */
    public function assist(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('auditlog.logs.view'), 403);

        $validated = $request->validate([
            'action'  => ['required', 'string', 'max:128'],
            'context' => ['sometimes', 'array'],
            'locale'  => ['sometimes', 'string', 'max:8'],
        ]);

        $guidance = $this->assistant->getGuidance(
            module:   'AuditLog',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            // Chantier 19 Lot 3: same phantom `users.role` bug fixed
            // identically on the sibling AI/Security AI-assist controllers
            // in this same pass — `role` is never populated by the real
            // registration flow (real RBAC is Spatie roles).
            userRole: $request->user()->getRoleNames()->first() ?? 'user',
        );

        return response()->json($guidance);
    }
}
