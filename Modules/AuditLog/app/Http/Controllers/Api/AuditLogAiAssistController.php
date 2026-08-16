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
     * @bodyParam action string required Action key (e.g. view_logs, export_audit, anomaly_detection, compliance_report). Example: view_logs
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
            userRole: $request->user()->role ?? 'user',
        );

        return response()->json($guidance);
    }
}
