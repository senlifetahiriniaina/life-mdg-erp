<?php

namespace Modules\Achats\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Achats\Events\RFQClosed;
use Modules\Achats\Events\RFQIssued;
use Modules\Achats\Events\SupplierQuoteAccepted;
use Modules\Achats\Events\SupplierQuoteReceived;
use Modules\Achats\Events\SupplierQuoteRejected;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\RFQLine;
use Modules\Achats\Models\SupplierQuote;

class RFQService
{
    public function createRFQ(array $data): RFQ
    {
        $data['rfq_number'] = $this->generateRFQNumber();
        $data['status'] = $data['status'] ?? 'draft';

        return RFQ::create($data);
    }

    public function updateRFQ(RFQ $rfq, array $data): RFQ
    {
        if (! $rfq->isDraft()) {
            throw new \Exception('Cannot update RFQ that is not in draft status');
        }

        $rfq->update($data);

        return $rfq;
    }

    public function addLineToRFQ(RFQ $rfq, array $lineData): RFQLine
    {
        if (! $rfq->isDraft()) {
            throw new \Exception('Cannot add lines to non-draft RFQ');
        }

        return $rfq->lines()->create($lineData);
    }

    public function removeLineFromRFQ(RFQLine $line): bool
    {
        if (! $line->rfq->isDraft()) {
            throw new \Exception('Cannot remove line from non-draft RFQ');
        }

        return $line->delete();
    }

    public function issueRFQ(RFQ $rfq, array $supplierIds): void
    {
        if (! $rfq->isDraft()) {
            throw new \Exception('Only draft RFQs can be issued');
        }

        $rfq->update([
            'status' => 'sent',
            'issued_date' => now()->toDateString(),
        ]);

        // Create empty quote records for each supplier
        foreach ($supplierIds as $supplierId) {
            SupplierQuote::create([
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplierId,
                'quote_number' => $this->generateQuoteNumber(),
                'status' => 'draft',
                'created_by' => auth()->id(),
                // Chantier 19: a quote has no independent tenant identity —
                // inherits its parent RFQ's company.
                'company_id' => $rfq->company_id,
            ]);
        }

        event(new RFQIssued($rfq, $supplierIds));
    }

    public function recordSupplierQuote(RFQ $rfq, int $supplierId, array $quoteData): SupplierQuote
    {
        $quote = SupplierQuote::updateOrCreate(
            ['rfq_id' => $rfq->id, 'supplier_id' => $supplierId],
            array_merge($quoteData, [
                'status' => 'submitted',
                'quote_number' => $this->generateQuoteNumber(),
                'created_by' => auth()->id(),
                'company_id' => $rfq->company_id,
            ])
        );

        event(new SupplierQuoteReceived($rfq, $quote));

        return $quote;
    }

    public function evaluateQuotes(RFQ $rfq): array
    {
        $quotes = $rfq->quotes()->where('status', 'submitted')->get();

        if ($quotes->isEmpty()) {
            return [];
        }

        $sorted = $quotes->sortBy('total_price')->values();

        return $sorted->map(function ($quote, $index) {
            return [
                'id' => $quote->id,
                'supplier_id' => $quote->supplier_id,
                'supplier_name' => $quote->supplier->name,
                'unit_price' => $quote->unit_price,
                'total_price' => $quote->total_price,
                'delivery_days' => $quote->delivery_days,
                'terms' => $quote->terms,
                'rank' => $index + 1,
            ];
        })->toArray();
    }

    public function selectWinningQuote(SupplierQuote $quote): void
    {
        // Reject other quotes for this RFQ
        SupplierQuote::where('rfq_id', $quote->rfq_id)
            ->where('id', '!=', $quote->id)
            ->update(['status' => 'rejected']);

        $quote->accept();

        event(new SupplierQuoteAccepted($quote->rfq, $quote));
    }

    public function rejectQuote(SupplierQuote $quote, string $reason): void
    {
        $quote->reject();

        event(new SupplierQuoteRejected($quote, $reason));
    }

    public function getQuoteComparison(RFQ $rfq): array
    {
        $quotes = $rfq->quotes()->where('status', 'submitted')->get();

        if ($quotes->isEmpty()) {
            return [];
        }

        return [
            'rfq_number' => $rfq->rfq_number,
            'rfq_description' => $rfq->description,
            'total_quotes' => $quotes->count(),
            'lowest_price' => $quotes->min('total_price'),
            'highest_price' => $quotes->max('total_price'),
            'average_price' => $quotes->avg('total_price'),
            'quotes' => $this->evaluateQuotes($rfq),
        ];
    }

    public function closeRFQ(RFQ $rfq): void
    {
        $rfq->update(['status' => 'closed']);

        event(new RFQClosed($rfq));
    }

    public function getExpiredRFQs(): Collection
    {
        return RFQ::where('deadline_date', '<', now()->toDateString())
            ->where('status', '!=', 'closed')
            ->get();
    }

    public function generateRFQNumber(): string
    {
        $format = config('achats.rfq_number_format', 'RFQ-{YYYY}-{MM}-{SEQUENCE}');
        $sequence = RFQ::count() + 1;

        return str_replace(
            ['{YYYY}', '{MM}', '{SEQUENCE}'],
            [now()->year, now()->format('m'), str_pad($sequence, 5, '0', STR_PAD_LEFT)],
            $format
        );
    }

    public function generateQuoteNumber(): string
    {
        return 'QUOTE-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
    }
}
