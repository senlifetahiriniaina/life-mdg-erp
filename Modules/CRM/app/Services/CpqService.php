<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Modules\CRM\Models\Quote;
use Modules\CRM\Models\QuoteLine;

class CpqService
{
    /**
     * Create a new quote with a generated reference QT-YYYY-XXXX.
     *
     * @param  array<string,mixed>  $data
     */
    public function createQuote(array $data): Quote
    {
        $year = now()->format('Y');
        $seq = str_pad((string) (Quote::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);

        $data['reference'] = $data['reference'] ?? "QT-{$year}-{$seq}";
        $data['status'] = $data['status'] ?? 'draft';
        $data['subtotal'] = $data['subtotal'] ?? '0.00';
        $data['discount_amount'] = $data['discount_amount'] ?? '0.00';
        $data['tax_amount'] = $data['tax_amount'] ?? '0.00';
        $data['total'] = $data['total'] ?? '0.00';

        return Quote::create($data);
    }

    /**
     * Add a line to a quote and recalculate.
     *
     * @param  array<string,mixed>  $lineData
     */
    public function addLine(Quote $q, array $lineData): QuoteLine
    {
        $qty = (float) ($lineData['quantity'] ?? 1);
        $price = (float) ($lineData['unit_price'] ?? 0);
        $discount = (float) ($lineData['discount_pct'] ?? 0);

        $lineTotal = $qty * $price * (1 - $discount / 100);

        $line = QuoteLine::create([
            'quote_id' => $q->id,
            'product_bundle_id' => $lineData['product_bundle_id'] ?? null,
            'description' => $lineData['description'] ?? '',
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_pct' => $discount,
            'line_total' => round($lineTotal, 2),
        ]);

        $this->recalculate($q);

        return $line;
    }

    /**
     * Recompute subtotal, tax (20%), total on the quote.
     */
    public function recalculate(Quote $q): Quote
    {
        $q->refresh();

        $subtotal = (float) $q->lines()->sum('line_total');
        $discountAmount = (float) $q->discount_amount;
        $taxable = $subtotal - $discountAmount;
        $taxAmount = round($taxable * 0.20, 2);
        $total = round($taxable + $taxAmount, 2);

        $q->update([
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        return $q->refresh();
    }

    /**
     * Generate an HTML representation of the quote.
     */
    public function generatePdf(Quote $q): string
    {
        $q->load(['lines.productBundle', 'contact', 'opportunity']);

        $linesHtml = '';
        foreach ($q->lines as $line) {
            $linesHtml .= sprintf(
                '<tr>
                    <td style="padding:8px;border-bottom:1px solid #eee">%s</td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:right">%s</td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:right">%s</td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:right">%s%%</td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:right"><strong>%s</strong></td>
                </tr>',
                htmlspecialchars($line->description),
                number_format((float) $line->quantity, 2),
                number_format((float) $line->unit_price, 2),
                number_format((float) $line->discount_pct, 2),
                number_format((float) $line->line_total, 2)
            );
        }

        $contactName = $q->contact
            ? htmlspecialchars($q->contact->first_name.' '.$q->contact->last_name)
            : 'N/A';

        $safeReference = htmlspecialchars((string) ($q->reference ?? ''));

        $notesHtml = $q->notes
            ? '<p style="margin-top:24px;color:#6b7280"><strong>Notes :</strong> '.htmlspecialchars($q->notes).'</p>'
            : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Devis {$safeReference}</title>
<style>
  body { font-family: Arial, sans-serif; color: #333; margin: 40px; }
  .header { display: flex; justify-content: space-between; margin-bottom: 32px; }
  .title { font-size: 28px; font-weight: bold; color: #4f46e5; }
  table { width: 100%; border-collapse: collapse; margin: 24px 0; }
  th { background: #f3f4f6; padding: 10px 8px; text-align: left; font-weight: 600; }
  .totals { margin-left: auto; width: 320px; }
  .totals td { padding: 6px 8px; }
  .total-row td { font-weight: bold; font-size: 16px; border-top: 2px solid #333; }
</style>
</head>
<body>
<div class="header">
  <div>
    <div class="title">DEVIS</div>
    <div style="color:#6b7280;margin-top:4px">Référence : <strong>{$safeReference}</strong></div>
  </div>
  <div style="text-align:right">
    <div>Contact : <strong>{$contactName}</strong></div>
    <div>Statut : <strong>{$q->status}</strong></div>
    <div>Valable jusqu'au : <strong>{$q->valid_until}</strong></div>
    <div style="color:#6b7280;font-size:12px">Émis le : {$q->created_at->format('d/m/Y')}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th style="text-align:right">Qté</th>
      <th style="text-align:right">P.U.</th>
      <th style="text-align:right">Remise</th>
      <th style="text-align:right">Total HT</th>
    </tr>
  </thead>
  <tbody>
    {$linesHtml}
  </tbody>
</table>

<table class="totals">
  <tr><td>Sous-total HT</td><td style="text-align:right">{$q->subtotal} €</td></tr>
  <tr><td>Remise</td><td style="text-align:right">- {$q->discount_amount} €</td></tr>
  <tr><td>TVA (20%)</td><td style="text-align:right">{$q->tax_amount} €</td></tr>
  <tr class="total-row"><td>TOTAL TTC</td><td style="text-align:right">{$q->total} €</td></tr>
</table>

{$notesHtml}
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Duplicate a quote (new reference, draft status, same lines).
     */
    public function duplicate(Quote $q): Quote
    {
        $q->load('lines');

        $year = now()->format('Y');
        $seq = str_pad((string) (Quote::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);

        $newQuote = Quote::create([
            'opportunity_id' => $q->opportunity_id,
            'contact_id' => $q->contact_id,
            'reference' => "QT-{$year}-{$seq}",
            'status' => 'draft',
            'valid_until' => $q->valid_until,
            'subtotal' => $q->subtotal,
            'discount_amount' => $q->discount_amount,
            'tax_amount' => $q->tax_amount,
            'total' => $q->total,
            'notes' => $q->notes,
        ]);

        foreach ($q->lines as $line) {
            QuoteLine::create([
                'quote_id' => $newQuote->id,
                'product_bundle_id' => $line->product_bundle_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount_pct' => $line->discount_pct,
                'line_total' => $line->line_total,
            ]);
        }

        return $newQuote;
    }
}
