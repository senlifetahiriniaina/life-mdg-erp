<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Modules\Accounting\Models\Invoice;
use Modules\Achats\Models\PurchaseOrder;

/**
 * Chantier 32 (volet A3) — balance âgée des fournisseurs ("aged payables"),
 * calculée en direct sur les vraies factures fournisseur (`Invoice`,
 * `type='bill'`, `partner_type='vendor'`, déjà utilisées par
 * PurchaseDepositService, Chantier 22).
 *
 * Aucun chiffre n'est jamais codé en dur ici — l'utilisateur a
 * explicitement demandé, via checkpoint, de construire l'outil sans les
 * données de la capture d'écran fournie. Sur une installation sans
 * facture fournisseur réelle, agedPayables() renvoie honnêtement une
 * liste vide.
 *
 * `amount_due` (colonne réelle sur `acc_invoices`) n'est écrite par aucun
 * chemin de code de cette application (confirmé par grep) — le solde dû
 * est donc systématiquement recalculé ici en direct (`total - amount_paid`),
 * jamais lu depuis cette colonne.
 */
class SupplierDebtService
{
    /**
     * @return array{
     *   suppliers: array<int, array{
     *     partner_id: int|null,
     *     partner_name: string|null,
     *     total_due: float,
     *     buckets: array{current: float, d31_60: float, d61_90: float, d90_plus: float},
     *     invoices: array<int, array{id: int, number: string|null, due_date: string|null, total: float, amount_paid: float, amount_due: float, days_overdue: int}>,
     *   }>,
     *   total_due: float,
     * }
     */
    public function agedPayables(): array
    {
        $today = Carbon::today();

        // Invoice n'a pas de company_id propre nulle part dans ce module
        // (confirmé — même convention que le reste d'Accounting, qui n'a
        // pas de gate `module:` dédié, voir CLAUDE.md).
        $invoices = Invoice::query()
            ->where('type', 'bill')
            ->where('partner_type', 'vendor')
            ->get()
            ->filter(fn (Invoice $invoice) => round((float) $invoice->total - (float) $invoice->amount_paid, 2) > 0.0);

        $bySupplier = [];

        foreach ($invoices as $invoice) {
            $key = $invoice->partner_id ?? ('name:'.$invoice->partner_name);

            $due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->startOfDay() : $today;
            // Carbon 3 flipped diffInDays()'s $absolute default from true
            // (Carbon 2) to false — a signed diff, negative when $dueDate
            // is before $today (same bug class already documented at
            // Chantier 19 Lot 2 for HR's calculateTenure()). Never rely on
            // the implicit default here — read the sign explicitly.
            $signedDiff = $today->diffInDays($dueDate, false);
            $daysOverdue = $signedDiff < 0 ? abs($signedDiff) : 0;

            if (! isset($bySupplier[$key])) {
                $bySupplier[$key] = [
                    'partner_id'   => $invoice->partner_id,
                    'partner_name' => $invoice->partner_name,
                    'total_due'    => 0.0,
                    'buckets'      => ['current' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0],
                    'invoices'     => [],
                ];
            }

            $bySupplier[$key]['total_due'] += $due;

            $bucket = match (true) {
                $daysOverdue <= 30 => 'current',
                $daysOverdue <= 60 => 'd31_60',
                $daysOverdue <= 90 => 'd61_90',
                default            => 'd90_plus',
            };
            $bySupplier[$key]['buckets'][$bucket] += $due;

            $bySupplier[$key]['invoices'][] = [
                'id'           => $invoice->id,
                'number'       => $invoice->number ?? $invoice->invoice_number,
                'due_date'     => $invoice->due_date,
                'total'        => (float) $invoice->total,
                'amount_paid'  => (float) $invoice->amount_paid,
                'amount_due'   => $due,
                'days_overdue' => $daysOverdue,
                'payment_stage' => $this->relatedPurchaseOrderStage($invoice->id),
            ];
        }

        return [
            'suppliers' => array_values($bySupplier),
            'total_due' => round(array_sum(array_column($bySupplier, 'total_due')), 2),
        ];
    }

    /**
     * Une facture peut être l'acompte ou le solde d'une commande d'achat
     * réelle (Chantier 22) — quand c'est le cas, on expose le
     * `payment_stage` de cette commande pour distinguer "acompte dû" de
     * "solde dû" plutôt qu'une simple facture générique.
     */
    private function relatedPurchaseOrderStage(int $invoiceId): ?string
    {
        $order = PurchaseOrder::where('deposit_invoice_id', $invoiceId)
            ->orWhere('balance_invoice_id', $invoiceId)
            ->first();

        return $order?->payment_stage;
    }
}
