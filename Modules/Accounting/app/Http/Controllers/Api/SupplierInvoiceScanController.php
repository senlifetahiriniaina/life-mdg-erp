<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\SupplierInvoiceScanService;

/**
 * Chantier 32 (volet C) — capture d'une facture fournisseur par scan/photo/PDF.
 *
 * Patron preview→commit à deux étapes, calqué exactement sur
 * `TreasuryImportController`/`StockImportController` déjà établis dans cette
 * app : preview() envoie le fichier à l'IA (Claude vision) et ne persiste
 * rien — l'utilisateur relit/corrige les champs extraits dans un vrai
 * formulaire ; commit() est le seul point d'écriture, ré-accepte le fichier
 * (upload multipart frais, l'aperçu ne le conserve pas côté serveur) plus les
 * champs éventuellement corrigés par l'utilisateur, et crée une vraie
 * `Invoice` type=bill/partner_type=vendor.
 */
class SupplierInvoiceScanController extends Controller
{
    public function __construct(private SupplierInvoiceScanService $service) {}

    /** POST /accounting/supplier-invoice-scan/preview */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $validated['file'];

        if (! $this->service->isSupportedFile($file)) {
            return response()->json([
                'message' => 'Format non supporté. Formats acceptés : JPEG, PNG, WEBP, PDF (10 Mo max).',
            ], 422);
        }

        $result = $this->service->extract($file);

        return response()->json($result);
    }

    /** POST /accounting/supplier-invoice-scan/commit */
    public function commit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'nullable|file|max:10240',
            'number' => 'nullable|string|max:100',
            'partner_name' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string|size:3',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $scanPath = null;
        if ($request->hasFile('file')) {
            $scanPath = $request->file('file')->store('supplier-invoice-scans', 'local');
        }

        $subtotal = 0.0;
        $taxAmount = 0.0;
        foreach ($validated['lines'] as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) $line['unit_price'];
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $lineSubtotal = $qty * $price;
            $subtotal += $lineSubtotal;
            $taxAmount += $lineSubtotal * ($taxRate / 100);
        }

        $invoice = Invoice::create([
            // `Invoice::count() + 1` (the pattern InvoiceController::store() itself
            // uses) collides once a same-year row has ever been soft-deleted —
            // confirmed empirically via a real duplicate-key SQL error on a second
            // scan commit in the same test run. A timestamp-to-the-second suffix
            // (same convention as SalesDepositService::generateInvoiceNumber())
            // isn't collision-proof either for two commits within the same
            // second, so a short random suffix is added on top.
            'number' => $validated['number'] ?? ('BILL-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4))),
            'type' => 'bill',
            'partner_type' => 'vendor',
            'partner_name' => $validated['partner_name'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'currency' => $validated['currency'],
            'status' => 'draft',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'amount_paid' => 0,
            'scan_path' => $scanPath,
            'created_by' => $request->user()?->id,
        ]);

        foreach ($validated['lines'] as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) $line['unit_price'];
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $lineSubtotal = $qty * $price;
            $lineTax = $lineSubtotal * ($taxRate / 100);

            $invoice->lineItems()->create([
                'description' => $line['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
                'tax_percent' => $taxRate,
                'subtotal' => $lineSubtotal,
                'tax_amount' => $lineTax,
                'total' => $lineSubtotal + $lineTax,
            ]);
        }

        return response()->json(['data' => $invoice->load('lineItems')], 201);
    }
}
