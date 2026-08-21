<?php

declare(strict_types=1);

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Achats\Models\PurchaseInvoiceMatch;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Services\ThreeWayMatchService;

/**
 * Chantier 32.13 (layer 9 — fake/dead audit): this controller/service/model
 * were fully written and unit-tested but had ZERO routes registered
 * anywhere in the app (confirmed via `php artisan route:list`), and zero
 * frontend consumer (confirmed via grep) — a real, self-contained,
 * low-risk "to activate" case, unlike PurchaseIntegrationService (still
 * left as a documented Category-C gap — see this chantier's own report).
 * Activated here: real routes, real company scoping (PurchaseInvoiceMatch
 * has no company_id column of its own — scoped through its real
 * purchaseOrder.company_id/purchaseReceipt.company_id relations instead,
 * matching this module's established ScopesToCompany convention), and a
 * real bug fixed in ThreeWayMatchService (see its own docblock) that would
 * have made every real match wrongly flag as a quantity mismatch.
 */
class ThreeWayMatchController extends Controller
{
    use ScopesToCompany;

    public function __construct(
        private readonly ThreeWayMatchService $matchService
    ) {}

    /**
     * Perform three-way match for a receipt.
     *
     * POST /purchase-receipts/{receipt}/match
     */
    public function match(Request $request, PurchaseReceipt $receipt): JsonResponse
    {
        $this->assertSameCompany($request, $receipt);

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
     * GET /invoice-matches/flagged
     */
    public function flagged(Request $request): JsonResponse
    {
        $matches = $this->matchService->getFlaggedMatches($this->companyId($request));

        return response()->json([
            'data' => $matches->values(),
            'meta' => ['total' => $matches->count()],
        ]);
    }

    /**
     * A PurchaseInvoiceMatch has no company_id column of its own (see class
     * docblock) — the real tenant boundary is its linked purchaseOrder's.
     */
    private function assertMatchSameCompany(Request $request, PurchaseInvoiceMatch $match): void
    {
        $match->loadMissing('purchaseOrder');
        abort_unless($match->purchaseOrder?->company_id === $this->companyId($request), 404);
    }

    /**
     * Resolve a flagged match.
     *
     * POST /invoice-matches/{match}/resolve
     */
    public function resolve(Request $request, PurchaseInvoiceMatch $match): JsonResponse
    {
        $this->assertMatchSameCompany($request, $match);

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
     * GET /invoice-matches/{match}
     */
    public function show(Request $request, PurchaseInvoiceMatch $match): JsonResponse
    {
        $this->assertMatchSameCompany($request, $match);

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
     * GET /invoice-matches/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->matchService->getMatchingStats($this->companyId($request))]);
    }

    /**
     * Get matches filtered by status.
     *
     * GET /invoice-matches/by-status?status=flagged
     */
    public function byStatus(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');

        $matches = PurchaseInvoiceMatch::where('status', $status)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('company_id', $this->companyId($request)))
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
     * GET /invoice-matches/by-result?result=matched
     */
    public function byResult(Request $request): JsonResponse
    {
        $result = $request->input('result', 'matched');

        $matches = PurchaseInvoiceMatch::where('match_result', $result)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('company_id', $this->companyId($request)))
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
     * POST /invoice-matches/bulk-resolve
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
            $companyId = $this->companyId($request);

            foreach ($validated['match_ids'] as $matchId) {
                $match = PurchaseInvoiceMatch::with('purchaseOrder')->find($matchId);
                // Chantier 32.13: a match belonging to another company is
                // silently skipped (not counted, no error revealing its
                // existence) rather than aborting the whole batch — matches
                // this method's own existing "skip if not flagged" pattern
                // for a match_id that doesn't qualify.
                if ($match && $match->status === 'flagged' && $match->purchaseOrder?->company_id === $companyId) {
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
