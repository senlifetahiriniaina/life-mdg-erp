<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Accounting\Services\InvoiceApprovalService;
use Modules\Achats\Services\ApprovalRoutingService;
use Modules\Validation\Models\ApprovalWorkflow;

/**
 * Setup wizard's "workflows" step needs to show Achats/Accounting's
 * amount-threshold rules for editing, but neither workflow is seeded
 * until a real purchase order/invoice is first submitted — an admin
 * visiting Setup before that has nothing to see or edit. This endpoint
 * ensures the workflow exists (idempotent, same firstOrCreate() pattern
 * each service already uses) and returns it with its rules.
 *
 * Rule edits themselves go straight through the existing, admin-gated
 * Validation API (PUT /api/v1/validation/approval-workflows/{workflow}/
 * rules/{rule}) — this controller only bridges "show me the seeded
 * threshold rules for module X", not a duplicate write path.
 */
class SetupThresholdsController extends Controller
{
    public function __construct(
        private readonly ApprovalRoutingService $achatsRouting,
        private readonly InvoiceApprovalService $invoiceApproval,
    ) {}

    public function show(string $module): JsonResponse
    {
        $workflow = match ($module) {
            'achats' => $this->seedAchats(),
            'accounting' => $this->invoiceApproval->getOrCreateWorkflow(),
            default => null,
        };

        if (! $workflow) {
            return response()->json(['message' => "Unknown module: {$module}"], 404);
        }

        return response()->json(['data' => $workflow->load('rules')]);
    }

    private function seedAchats(): ApprovalWorkflow
    {
        $this->achatsRouting->createDefaultWorkflows();

        // createDefaultWorkflows() also seeds a secondary "Emergency PO
        // Approval" workflow; Setup's threshold step exposes only the
        // primary tiered workflow, matching Accounting's single-workflow
        // shape.
        return ApprovalWorkflow::where('module_name', 'Achats')
            ->where('name', 'Standard PO Approval')
            ->firstOrFail();
    }
}
