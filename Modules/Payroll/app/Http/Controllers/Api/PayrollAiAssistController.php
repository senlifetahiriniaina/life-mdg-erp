<?php

declare(strict_types=1);

namespace Modules\Payroll\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\Services\AiContextualAssistantService;

/**
 * @group Payroll — AI Assist
 *
 * Contextual AI guidance for payroll processing actions.
 */
class PayrollAiAssistController extends Controller
{
    public function __construct(
        private readonly AiContextualAssistantService $assistant,
    ) {}

    /**
     * POST /api/v1/payroll/ai/assist
     *
     * Returns contextual AI guidance for a payroll action.
     *
     * @bodyParam action string required Action key (e.g. generate_payslips, approve_payroll, run_payroll, post_to_accounting). Example: run_payroll
     * @bodyParam context array Optional current context data. Example: {"period": "2026-05", "employee_count": 45}
     * @bodyParam locale string Locale for the response. Example: fr
     */
    public function assist(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payroll.payslip.view'), 403);

        $validated = $request->validate([
            'action'  => ['required', 'string', 'max:128'],
            'context' => ['sometimes', 'array'],
            'locale'  => ['sometimes', 'string', 'max:8'],
        ]);

        // Chantier 8.3: was hardcoded to module 'HR' — this is Payroll's own
        // AI-assist endpoint, and AiContextualAssistantService's static
        // fallback table has a real 'Payroll' module entry (generate_payslips/
        // approve_payroll/...) that this endpoint was never actually reaching.
        $guidance = $this->assistant->getGuidance(
            module:   'Payroll',
            action:   $validated['action'],
            context:  $validated['context'] ?? [],
            locale:   $validated['locale'] ?? 'fr',
            userRole: $request->user()->role ?? 'user',
        );

        return response()->json($guidance);
    }
}
