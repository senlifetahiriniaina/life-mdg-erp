<?php

declare(strict_types=1);

namespace Modules\Achats\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Achats\Models\PurchaseInvoiceMatch;
use Modules\Achats\Models\PurchaseReceipt;

class ThreeWayMatchService
{
    private const QUANTITY_TOLERANCE = 0.05; // 5% tolerance
    private const PRICE_TOLERANCE    = 0.05; // 5% tolerance

    /**
     * Perform three-way match: PO ↔ PurchaseReceipt ↔ Invoice
     */
    public function matchInvoiceWithReceipt(
        PurchaseReceipt $receipt,
        array $invoiceData
    ): PurchaseInvoiceMatch {
        try {
            return DB::transaction(function () use ($receipt, $invoiceData) {
                $po = $receipt->purchaseOrder;

                // Extract invoice details
                $invoiceQuantity = (float) ($invoiceData['quantity'] ?? 0);
                $invoicePrice    = (float) ($invoiceData['total_amount'] ?? 0);
                $invoiceId       = $invoiceData['id'] ?? null;

                // PO totals
                // Chantier 32.13: confirmed empirically (tinker) that
                // achats_purchase_order_lines has never had a
                // 'quantity_ordered' column — the real column is
                // 'quantity' — so this always summed to 0, forcing the
                // `?: 1` fallback below on every real call and inflating
                // the quantity-variance percentage by the PO's true
                // ordered quantity (a 0.4-unit variance on a 10-unit PO
                // was scored as 40%, not the real 4%, wrongly flagging
                // every match with quantity > 1 as a mismatch).
                $poTotalAmount   = (float) $po->total;
                $poTotalQuantity = (float) $po->lines->sum('quantity');

                // Calculate variances
                $receivedQty      = (float) $receipt->getTotalReceived();
                $quantityVariance = $receivedQty - $invoiceQuantity;
                $priceVariance    = $poTotalAmount - $invoicePrice;

                // Determine match result
                $matchResult = $this->determineMatchResult(
                    $quantityVariance,
                    $priceVariance,
                    $poTotalQuantity,
                    $poTotalAmount
                );

                $mismatchDetails = $this->generateMismatchDetails(
                    $matchResult,
                    $quantityVariance,
                    $priceVariance,
                    $receivedQty,
                    $invoiceQuantity
                );

                $status = $this->determineMatchStatus($matchResult);

                // Create match record
                $match = PurchaseInvoiceMatch::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_id'   => $po->id,
                    'invoice_id'          => $invoiceId,
                    'quantity_variance'   => $quantityVariance,
                    'price_variance'      => $priceVariance,
                    'match_result'        => $matchResult,
                    'mismatch_details'    => $mismatchDetails ? ['details' => $mismatchDetails] : null,
                    'status'              => $status,
                ]);

                Log::info('Three-way match completed', [
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_id'   => $po->id,
                    'match_result'        => $matchResult,
                    'status'              => $status,
                ]);

                return $match;
            });
        } catch (\Exception $e) {
            Log::error('Three-way match failed', [
                'purchase_receipt_id' => $receipt->id,
                'error'               => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Determine match result based on variances against PO totals.
     */
    private function determineMatchResult(
        float $quantityVariance,
        float $priceVariance,
        float $poQuantity,
        float $poAmount
    ): string {
        $quantityVariancePct = abs($quantityVariance / ($poQuantity ?: 1));
        $priceVariancePct    = abs($priceVariance / ($poAmount ?: 1));

        $quantityMatches = $quantityVariancePct <= self::QUANTITY_TOLERANCE;
        $priceMatches    = $priceVariancePct <= self::PRICE_TOLERANCE;

        if ($quantityMatches && $priceMatches) {
            return 'matched';
        }

        if (!$quantityMatches && $priceMatches) {
            return 'quantity_mismatch';
        }

        if ($quantityMatches && !$priceMatches) {
            return 'price_mismatch';
        }

        return 'both_mismatch';
    }

    /**
     * Generate a human-readable mismatch description.
     */
    private function generateMismatchDetails(
        string $matchResult,
        float $quantityVariance,
        float $priceVariance,
        float $receivedQuantity,
        float $invoiceQuantity
    ): string {
        if ($matchResult === 'matched') {
            return '';
        }

        $details = ["Match Result: {$matchResult}"];

        if (str_contains($matchResult, 'quantity')) {
            $details[] = "Quantity Variance: {$quantityVariance} units";
            $details[] = "Received: {$receivedQuantity}, Invoiced: {$invoiceQuantity}";
        }

        if (str_contains($matchResult, 'price')) {
            $details[] = 'Price Variance: ' . number_format($priceVariance, 2);
        }

        $details[] = 'Timestamp: ' . now()->toIso8601String();

        return implode("\n", $details);
    }

    /**
     * Map match result to initial status (approved when matched, flagged otherwise).
     */
    private function determineMatchStatus(string $matchResult): string
    {
        return $matchResult === 'matched' ? 'approved' : 'flagged';
    }

    /**
     * Get all flagged (unresolved) matches ordered by newest first.
     *
     * Chantier 32.13: PurchaseInvoiceMatch has no company_id column of its
     * own (only the never-populated tenant_id) — this module's other
     * resources scope directly, this one scopes through its real
     * purchaseOrder.company_id relation instead. $companyId is optional
     * (defaults to no filter) so this method's existing direct-call unit
     * tests keep working unchanged; the controller always passes a real one.
     *
     * @return Collection<int, PurchaseInvoiceMatch>
     */
    public function getFlaggedMatches(?int $companyId = null): Collection
    {
        return PurchaseInvoiceMatch::where('status', 'flagged')
            ->when($companyId !== null, fn ($q) => $q->whereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('company_id', $companyId)))
            ->with('purchaseOrder.supplier', 'purchaseReceipt')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Resolve a flagged mismatch, optionally appending notes.
     */
    public function resolveMismatch(
        PurchaseInvoiceMatch $match,
        int $resolvedByUserId,
        string $status = 'approved',
        ?string $notes = null
    ): bool {
        try {
            return DB::transaction(function () use ($match, $resolvedByUserId, $status, $notes) {
                $match->resolve($resolvedByUserId, $status);

                if ($notes) {
                    $existing = $match->mismatch_details ?? [];
                    $existing['resolution'] = $notes;
                    $match->update(['mismatch_details' => $existing, 'resolution_notes' => $notes]);
                }

                Log::info('Mismatch resolved', [
                    'match_id'    => $match->id,
                    'resolved_by' => $resolvedByUserId,
                    'status'      => $status,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to resolve mismatch', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Return aggregate statistics for three-way matching.
     *
     * @return array<string, mixed>
     */
    public function getMatchingStats(?int $companyId = null): array
    {
        $scope = fn () => PurchaseInvoiceMatch::when(
            $companyId !== null,
            fn ($q) => $q->whereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('company_id', $companyId))
        );

        $total    = $scope()->count();
        $matched  = $scope()->where('match_result', 'matched')->count();
        $flagged  = $scope()->where('status', 'flagged')->count();
        $resolved = $scope()->where('status', 'resolved')->count();

        return [
            'total_matches'  => $total,
            'matched_count'  => $matched,
            'flagged_count'  => $flagged,
            'resolved_count' => $resolved,
            'match_rate'     => $total > 0 ? ($matched / $total) * 100 : 0,
            'exception_rate' => $total > 0 ? ($flagged / $total) * 100 : 0,
        ];
    }

    /**
     * Returns true if the invoice linked to the given match may be processed.
     */
    public function canProcessInvoice(PurchaseInvoiceMatch $match): bool
    {
        return $match->status === 'approved' || $match->match_result === 'matched';
    }

    /**
     * Return a list of human-readable blocking issues for the given match.
     *
     * @return list<string>
     */
    public function getBlockingIssues(PurchaseInvoiceMatch $match): array
    {
        $issues = [];

        if ($match->match_result !== 'matched') {
            $issues[] = "Match Status: {$match->match_result}";

            if ((float) $match->quantity_variance !== 0.0) {
                $issues[] = "Quantity discrepancy: {$match->quantity_variance} units";
            }

            if ((float) $match->price_variance !== 0.0) {
                $issues[] = 'Price discrepancy: ' . number_format((float) $match->price_variance, 2);
            }
        }

        return $issues;
    }
}
