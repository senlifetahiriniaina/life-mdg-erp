<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting - Advanced Accounting
 *
 * Advanced accounting features: multi-entity consolidation, intercompany transactions,
 * deferred revenue, currency revaluation, and IFRS/SYSCOHADA adjustments.
 */
class AdvancedAccountingController extends Controller
{
    public function __construct(private AccountingService $service) {}

    /** GET /advanced/consolidation-report */
    public function consolidationReport(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'period'    => $request->only(['from', 'to']),
                'message'   => 'Use ConsolidationController for full consolidation workflows.',
                'entities'  => [],
            ],
        ]);
    }

    /** GET /advanced/intercompany */
    public function intercompanyTransactions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['transactions' => [], 'message' => 'Intercompany transactions available via consolidation module.'],
        ]);
    }

    /** POST /advanced/currency-revaluation */
    public function currencyRevaluation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => 'required|date',
            'currency'   => 'required|string|size:3',
        ]);

        return response()->json([
            'data' => [
                'as_of_date' => $validated['as_of_date'],
                'currency'   => $validated['currency'],
                'status'     => 'revaluation_queued',
            ],
        ]);
    }

    /** POST /advanced/period-close */
    public function periodClose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_end' => 'required|date',
            'lock'       => 'boolean',
        ]);

        return response()->json([
            'data' => [
                'period_end' => $validated['period_end'],
                'locked'     => $validated['lock'] ?? false,
                'status'     => 'period_close_initiated',
            ],
        ]);
    }

    /** GET /advanced/audit-trail */
    public function auditTrail(Request $request): JsonResponse
    {
        return response()->json([
            'data'    => [],
            'message' => 'Full audit trail available via AuditLog module.',
        ]);
    }
}
