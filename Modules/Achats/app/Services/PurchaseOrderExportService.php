<?php

namespace Modules\Achats\Services;

use Illuminate\Support\Facades\View;
use Modules\Achats\Models\PurchaseOrder;

class PurchaseOrderExportService
{
    public function generatePdfUrl(PurchaseOrder $po): string
    {
        // Generate a route that will serve the PDF
        return route('achats.purchase-orders.export-pdf', $po);
    }

    public function generatePdfContent(PurchaseOrder $po): string
    {
        $po->load(['supplier', 'lines', 'requester', 'approver']);

        $html = View::make('achats::exports.purchase-order-pdf', [
            'po' => $po,
            'company' => config('app.name'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ])->render();

        return $html;
    }

    public function getPoSummary(PurchaseOrder $po): array
    {
        $po->load(['supplier', 'lines']);

        return [
            'po_number' => $po->po_number,
            'supplier' => [
                'name' => $po->supplier->name,
                'email' => $po->supplier->email,
                'phone' => $po->supplier->phone,
                'payment_terms' => $po->supplier->payment_terms,
            ],
            'order_details' => [
                'order_date' => $po->order_date->toDateString(),
                'delivery_date' => $po->delivery_date?->toDateString(),
                'currency' => $po->currency,
                'status' => $po->status,
            ],
            'line_items' => $po->lines->map(function ($line) {
                return [
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_price' => $line->unit_price,
                    'line_total' => $line->line_total,
                    'tax_rate' => $line->tax_rate,
                    'tax_amount' => $line->line_total * ($line->tax_rate / 100),
                ];
            }),
            'totals' => [
                'subtotal' => $po->subtotal,
                'tax_amount' => $po->tax_amount,
                'shipping_cost' => $po->shipping_cost,
                'total' => $po->total,
            ],
            'notes' => $po->notes,
        ];
    }

    public function exportToJson(PurchaseOrder $po): string
    {
        return json_encode($this->getPoSummary($po), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function exportToCsv(PurchaseOrder $po): string
    {
        $po->load(['lines']);

        $csv = "\"PURCHASE ORDER: {$po->po_number}\"\n";
        $csv .= "\"Supplier:\",\"{$po->supplier->name}\"\n";
        $csv .= "\"Order Date:\",\"{$po->order_date->toDateString()}\"\n";
        $csv .= "\"Delivery Date:\",\"{$po->delivery_date?->toDateString()}\"\n";
        $csv .= "\n";

        $csv .= "\"Description\",\"Quantity\",\"Unit\",\"Unit Price\",\"Line Total\",\"Tax Rate\",\"Tax Amount\"\n";

        foreach ($po->lines as $line) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                str_replace('"', '""', $line->description),
                $line->quantity,
                $line->unit,
                $line->unit_price,
                $line->line_total,
                $line->tax_rate,
                $line->line_total * ($line->tax_rate / 100)
            );
        }

        $csv .= "\n";
        $csv .= "\"Subtotal\",,,,,\"{$po->subtotal}\"\n";
        $csv .= "\"Tax Amount\",,,,,\"{$po->tax_amount}\"\n";
        $csv .= "\"Shipping\",,,,,\"{$po->shipping_cost}\"\n";
        $csv .= "\"TOTAL\",,,,,\"{$po->total}\"\n";

        return $csv;
    }
}
