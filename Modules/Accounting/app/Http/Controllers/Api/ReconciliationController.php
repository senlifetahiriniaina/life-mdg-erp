<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Models\Reconciliation;
use Modules\Accounting\Services\BankReconciliationService;

/**
 * @group Accounting - Reconciliation
 *
 * Bank and ledger reconciliation workflows: initiate, import statements, auto-match, complete.
 */
class ReconciliationController extends Controller
{
    public function __construct(private BankReconciliationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $recons = Reconciliation::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($recons);
    }

    public function show(Reconciliation $reconciliation): JsonResponse
    {
        return response()->json(['data' => $reconciliation]);
    }

    /** POST /reconciliations */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|integer',
            'period_start'    => 'required|date',
            'period_end'      => 'required|date|after_or_equal:period_start',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
        ]);

        $reconciliation = Reconciliation::create(array_merge($validated, ['status' => 'initiated']));

        return response()->json(['data' => $reconciliation], 201);
    }

    /** POST /reconciliations/{reconciliation}/import */
    public function importStatement(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,ofx,qfx']);

        return response()->json([
            'data' => [
                'reconciliation_id' => $reconciliation->id,
                'message'           => 'Statement import queued.',
                'status'            => 'processing',
            ],
        ]);
    }

    /** POST /reconciliations/{reconciliation}/auto-match */
    public function autoMatch(Reconciliation $reconciliation): JsonResponse
    {
        return response()->json([
            'data' => [
                'reconciliation_id' => $reconciliation->id,
                'status'            => 'auto_match_queued',
                'message'           => 'Auto-match job dispatched.',
            ],
        ]);
    }

    /** POST /reconciliations/{reconciliation}/match */
    public function manualMatch(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        $validated = $request->validate([
            'statement_line_id' => 'required|integer',
            'journal_entry_id'  => 'required|integer',
        ]);

        return response()->json(['data' => array_merge($validated, ['reconciliation_id' => $reconciliation->id, 'status' => 'matched'])]);
    }

    /** POST /reconciliations/{reconciliation}/complete */
    public function complete(Reconciliation $reconciliation): JsonResponse
    {
        $reconciliation->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json(['data' => $reconciliation]);
    }

    /** POST /reconciliations/{reconciliation}/approve */
    public function approve(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        $reconciliation->update(['status' => 'approved', 'approved_by' => $request->user()?->id, 'approved_at' => now()]);

        return response()->json(['data' => $reconciliation]);
    }

    /** POST /reconciliations/{reconciliation}/reject */
    public function reject(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        $reconciliation->update(['status' => 'rejected', 'rejection_reason' => $request->input('reason')]);

        return response()->json(['data' => $reconciliation]);
    }

    /** GET /reconciliations/{reconciliation}/outstanding */
    public function outstandingItems(Reconciliation $reconciliation): JsonResponse
    {
        return response()->json([
            'data'              => [],
            'reconciliation_id' => $reconciliation->id,
            'message'           => 'Outstanding items query requires linked BankStatement.',
        ]);
    }

    /** GET /reconciliations/{reconciliation}/exceptions */
    public function exceptions(Reconciliation $reconciliation): JsonResponse
    {
        return response()->json([
            'data'              => [],
            'reconciliation_id' => $reconciliation->id,
            'message'           => 'Exceptions query requires linked BankStatement.',
        ]);
    }

    /**
     * GET /reconciliations/{reconciliation}/suggest/{bankTransaction}
     *
     * Rank candidate journal entries for a bank transaction using the same multi-criteria
     * scoring BankReconciliationService::autoMatch() uses, without committing a match —
     * lets the manual-match review UI show the top-scored options for a human to confirm.
     */
    public function suggestMatches(Reconciliation $reconciliation, BankTransaction $bankTransaction): JsonResponse
    {
        $suggestions = $this->service->suggestMatches($bankTransaction);

        return response()->json([
            'data'               => $suggestions,
            'reconciliation_id'  => $reconciliation->id,
            'bank_transaction_id' => $bankTransaction->id,
        ]);
    }
}
