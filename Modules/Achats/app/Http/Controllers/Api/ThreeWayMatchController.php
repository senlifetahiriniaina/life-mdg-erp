<?php

declare(strict_types=1);

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Achats\Models\PurchaseInvoiceMatch;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Services\ThreeWayMatchService;

class ThreeWayMatchController extends Controller
{
    public function __construct(
        private readonly ThreeWayMatchService $matchService
    ) {}

    /**
     * Perform three-way match for a receipt.
     *
     * POST /api/achats/purchase-receipts/{receipt}/match
     */
    public function match(Request $request, PurchaseReceipt $receipt): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id'   => 'nullable|integer',
            'quantity'     => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        try {
            $match = $this->matchService->matchInvoiceWithReceipt($receipt, $validated);

            Log::info('Three-way match created', [
                'match_id'   => $match->id,
                'receipt_id' => $receipt->id,
            ]);

            return response()->json(['data' => $match], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create three-way match', [
                'receipt_id' => $receipt->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to create match'], 500);
        }
    }

    /**
     * Get all flagged matches awaiting review.
     *
     * GET /api/achats/invoice-matches/flagged
     */
    public function flagged(Request $request): JsonResponse
    {
        $matches = $this->matchService->getFlaggedMatches();

        return response()->json([
            'data' => $matches->values(),
            'meta' => ['total' => $matches->count()],
        ]);
    }

    /**
     * Resolve a flagged match.
     *
     * POST /api/achats/invoice-matches/{match}/resolve
     */
    public function resolve(Request $request, PurchaseInvoiceMatch $match): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,resolved',
            'notes'  => 'nullable|string|max:1000',
        ]);

        try {
            $this->matchService->resolveMismatch(
                $match,
                (int) auth()->id(),
                $validated['status'],
                $validated['notes'] ?? null
            );

            Log::info('Match resolved', [
                'match_id'    => $match->id,
                'resolved_by' => auth()->id(),
                'status'      => $validated['status'],
            ]);

            return response()->json([
                'data'    => $match->refresh(),
                'message' => 'Match resolved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resolve match', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to resolve match'], 500);
        }
    }

    /**
     * Show a single match with blocking issue analysis.
     *
     * GET /api/achats/invoice-matches/{match}
     */
    public function show(PurchaseInvoiceMatch $match): JsonResponse
    {
        $match->load('purchaseOrder.supplier', 'purchaseReceipt');

        return response()->json([
            'data' => [
                'match'               => $match,
                'can_process_invoice' => $this->matchService->canProcessInvoice($match),
                'blocking_issues'     => $this->matchService->getBlockingIssues($match),
            ],
        ]);
    }

    /**
     * Get aggregate matching statistics.
     *
     * GET /api/achats/invoice-matches/statistics
     */
    public function statistics(): JsonResponse
    {
        return response()->json(['data' => $this->matchService->getMatchingStats()]);
    }

    /**
     * Get matches filtered by status.
     *
     * GET /api/achats/invoice-matches/by-status?status=flagged
     */
    public function byStatus(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');

        $matches = PurchaseInvoiceMatch::where('status', $status)
            ->with('purchaseOrder.supplier', 'purchaseReceipt')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $matches->items(),
            'meta' => [
                'total'   => $matches->total(),
                'status'  => $status,
            ],
        ]);
    }

    /**
     * Get matches filtered by match_result.
     *
     * GET /api/achats/invoice-matches/by-result?result=matched
     */
    public function byResult(Request $request): JsonResponse
    {
        $result = $request->input('result', 'matched');

        $matches = PurchaseInvoiceMatch::where('match_result', $result)
            ->with('purchaseOrder.supplier', 'purchaseReceipt')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $matches->items(),
            'meta' => [
                'total'        => $matches->total(),
                'match_result' => $result,
            ],
        ]);
    }

    /**
     * Bulk-resolve multiple flagged matches.
     *
     * POST /api/achats/invoice-matches/bulk-resolve
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_ids'   => 'required|array|min:1',
            'match_ids.*' => 'integer|exists:achats_purchase_invoice_matches,id',
            'status'      => 'required|in:approved,resolved',
            'notes'       => 'nullable|string',
        ]);

        try {
            $updated = 0;

            foreach ($validated['match_ids'] as $matchId) {
                $match = PurchaseInvoiceMatch::find($matchId);
                if ($match && $match->status === 'flagged') {
                    $this->matchService->resolveMismatch(
                        $match,
                        (int) auth()->id(),
                        $validated['status'],
                        $validated['notes'] ?? null
                    );
                    $updated++;
                }
            }

            Log::info('Bulk matches resolved', [
                'count'  => $updated,
                'status' => $validated['status'],
            ]);

            return response()->json([
                'message' => "{$updated} matches resolved",
                'count'   => $updated,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to bulk resolve matches', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to bulk resolve matches'], 500);
        }
    }
}
