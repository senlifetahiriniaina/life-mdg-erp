<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Modules\Logistics\Models\FreightInvoice;
use Modules\Logistics\Models\Shipment;

class FreightBillingService
{
    public function generateInvoice(Shipment $shipment): FreightInvoice
    {
        $quoted = (float) ($shipment->estimated_cost ?? 0);

        return FreightInvoice::create([
            'shipment_id' => $shipment->id,
            'carrier_id' => $shipment->carrier_id,
            'type' => 'carrier_invoice',
            'status' => 'pending_review',
            'currency' => $shipment->value_currency ?? 'USD',
            'quoted_amount' => $quoted,
            'invoiced_amount' => $quoted,
            'variance_amount' => 0,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'invoice_number' => 'FI-'.now()->format('Ymd').'-'.str_pad(
                (string) (FreightInvoice::whereDate('created_at', today())->count() + 1),
                4,
                '0',
                STR_PAD_LEFT
            ),
            'created_by' => $shipment->created_by,
        ]);
    }

    /** @return array<string, mixed> */
    public function auditInvoice(FreightInvoice $invoice): array
    {
        $invoiced = (float) $invoice->invoiced_amount;
        $quoted = (float) ($invoice->quoted_amount ?? 0);
        $variance = $invoiced - $quoted;
        $variancePct = $quoted > 0 ? round($variance / $quoted * 100, 2) : null;
        $hasDiscrepancy = abs($variance) > 0.01;

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'carrier_id' => $invoice->carrier_id,
            'quoted_amount' => $quoted,
            'invoiced_amount' => $invoiced,
            'variance_amount' => round($variance, 2),
            'variance_pct' => $variancePct,
            'has_discrepancy' => $hasDiscrepancy,
            'recommendation' => $hasDiscrepancy
                ? ($variance > 0 ? 'dispute' : 'approve_with_credit')
                : 'approve',
        ];
    }

    public function approve(FreightInvoice $invoice, int $userId): void
    {
        $invoice->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);
    }

    public function dispute(FreightInvoice $invoice, string $reason): void
    {
        $invoice->update([
            'status' => 'disputed',
            'dispute_reason' => $reason,
        ]);
    }
}
