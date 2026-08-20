<?php

declare(strict_types=1);

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Payment;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Core\Services\ParticipantNotificationService;

/**
 * Chantier 22 (volet B) — miroir côté achats de Modules\Sales\Services\SalesDepositService :
 * commande de matières avec acompte 30% versé au fournisseur / solde 70% à
 * la livraison. Voir le docblock de classe de SalesDepositService pour le
 * raisonnement complet (réutilisation d'Invoice/Payment, simplification
 * comptable assumée sur le lettrage de l'avance).
 *
 * Traitement OHADA pour un acompte VERSÉ (pas reçu) : c'est une créance
 * (actif), pas une dette — Débit 4091 "Fournisseurs, avances et acomptes
 * versés" (nouveau, voir AccountingDatabaseSeeder) / Crédit trésorerie.
 */
class PurchaseDepositService
{
    public function __construct(private readonly ParticipantNotificationService $notifications) {}

    public function requestDeposit(PurchaseOrder $po, float $percent, ?int $userId): PurchaseOrder
    {
        if ($po->deposit_invoice_id !== null) {
            throw new \RuntimeException("Un acompte a déjà été demandé pour la commande {$po->po_number}.");
        }
        if ($percent <= 0 || $percent > 100) {
            throw new \RuntimeException('Le pourcentage d\'acompte doit être compris entre 0 et 100.');
        }

        $amount = round((float) $po->total * ($percent / 100), 2);
        $supplier = Supplier::find($po->supplier_id);
        $supplierName = $supplier?->name ?? 'Fournisseur';

        $invoice = Invoice::create([
            'number' => $this->generateInvoiceNumber('DEP', $po->po_number),
            'type' => 'bill',
            'partner_type' => 'vendor',
            'customer_id' => $po->supplier_id ?? 0,
            'partner_name' => $supplierName,
            'customer_name' => $supplierName,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'sent',
            'currency' => $po->currency ?? 'MGA',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'amount_paid' => 0,
            'amount_due' => $amount,
            'notes' => "Acompte {$percent}% — commande {$po->po_number}",
            'created_by' => $userId,
        ]);

        $po->update([
            'deposit_percent' => $percent,
            'deposit_required_amount' => $amount,
            'deposit_invoice_id' => $invoice->id,
        ]);

        return $po->fresh(['depositInvoice']);
    }

    public function requestBalance(PurchaseOrder $po, ?int $userId): PurchaseOrder
    {
        if ($po->balance_invoice_id !== null) {
            throw new \RuntimeException("Le solde a déjà été demandé pour la commande {$po->po_number}.");
        }

        $depositAmount = (float) ($po->deposit_required_amount ?? 0);
        $amount = round((float) $po->total - $depositAmount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Aucun solde restant à régler sur cette commande.');
        }

        $supplier = Supplier::find($po->supplier_id);
        $supplierName = $supplier?->name ?? 'Fournisseur';

        $invoice = Invoice::create([
            'number' => $this->generateInvoiceNumber('SLD', $po->po_number),
            'type' => 'bill',
            'partner_type' => 'vendor',
            'customer_id' => $po->supplier_id ?? 0,
            'partner_name' => $supplierName,
            'customer_name' => $supplierName,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'sent',
            'currency' => $po->currency ?? 'MGA',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'amount_paid' => 0,
            'amount_due' => $amount,
            'notes' => "Solde — commande {$po->po_number}",
            'created_by' => $userId,
        ]);

        $po->update(['balance_invoice_id' => $invoice->id]);

        return $po->fresh(['balanceInvoice']);
    }

    public function recordDepositPayment(PurchaseOrder $po, float $amount, ?string $method, ?string $reference, ?int $userId): PurchaseOrder
    {
        if ($po->deposit_invoice_id === null) {
            throw new \RuntimeException('Aucun acompte n\'a été demandé pour cette commande.');
        }

        $invoice = Invoice::findOrFail($po->deposit_invoice_id);

        // Chantier 22 empirical test run: same fix as SalesDepositService —
        // postJournalEntry() used to silently no-op when the chart of
        // accounts wasn't seeded, so a payment could be recorded with no
        // GL entry and no error. Wrapped in one transaction so a missing
        // journal/account rolls back the whole payment.
        DB::transaction(function () use ($invoice, $amount, $method, $reference, $userId, $po) {
            $this->applyPayment($invoice, $amount, $method, $reference, $userId);
            $this->postJournalEntry($po, $amount, 'purchase_deposit', $userId);
        });

        $this->notify($po, 'Acompte versé', "Un acompte de {$amount} " . ($po->currency ?? 'MGA') . " a été versé pour la commande {$po->po_number}.");

        return $po->fresh(['depositInvoice', 'balanceInvoice']);
    }

    public function recordBalancePayment(PurchaseOrder $po, float $amount, ?string $method, ?string $reference, ?int $userId): PurchaseOrder
    {
        if ($po->balance_invoice_id === null) {
            throw new \RuntimeException('Aucun solde n\'a été demandé pour cette commande.');
        }

        $invoice = Invoice::findOrFail($po->balance_invoice_id);

        DB::transaction(function () use ($invoice, $amount, $method, $reference, $userId, $po) {
            $this->applyPayment($invoice, $amount, $method, $reference, $userId);
            $this->postJournalEntry($po, $amount, 'purchase_balance', $userId);
        });

        $this->notify($po, 'Solde versé', "Le solde de {$amount} " . ($po->currency ?? 'MGA') . " a été versé pour la commande {$po->po_number}.");

        return $po->fresh(['depositInvoice', 'balanceInvoice']);
    }

    private function applyPayment(Invoice $invoice, float $amount, ?string $method, ?string $reference, ?int $userId): void
    {
        $currentPaid = (float) ($invoice->amount_paid ?? 0);
        $newPaid = $currentPaid + $amount;

        if ($newPaid > (float) $invoice->total + 0.01) {
            throw new \RuntimeException('Le montant du paiement dépasse le total de la facture.');
        }

        $invoice->update([
            'amount_paid' => $newPaid,
            'paid_amount' => $newPaid,
            'status' => $newPaid >= (float) $invoice->total ? 'paid' : $invoice->status,
            'paid_at' => $newPaid >= (float) $invoice->total ? now() : null,
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'currency' => $invoice->currency ?? 'MGA',
            'payment_method' => $method,
            'reference' => $reference,
            'status' => 'completed',
            'created_by' => $userId,
        ]);
    }

    /**
     * Débit 4091 (acompte) ou 401 (solde) / Crédit trésorerie (512) — sens
     * inversé par rapport à SalesDepositService puisqu'il s'agit d'un
     * décaissement, pas d'un encaissement. Échoue fort si le plan comptable
     * n'est pas seedé — voir le commentaire équivalent dans
     * SalesDepositService::postJournalEntry(). Appelée à l'intérieur du
     * DB::transaction() du site d'appel.
     */
    private function postJournalEntry(PurchaseOrder $po, float $amount, string $kind, ?int $userId): void
    {
        $journal = Journal::where('code', 'ACH')->first();
        $treasuryAccount = ChartOfAccount::where('code', '512')->first();
        $counterpartCode = $kind === 'purchase_deposit' ? '4091' : '401';
        $counterpartAccount = ChartOfAccount::where('code', $counterpartCode)->first();

        if ($journal === null || $treasuryAccount === null || $counterpartAccount === null) {
            throw new \RuntimeException('Plan comptable incomplet : journal ACH ou compte 512/4091/401 introuvable.');
        }

        $label = $kind === 'purchase_deposit'
            ? "Acompte versé — commande {$po->po_number}"
            : "Solde versé — commande {$po->po_number}";

        $sequence = JournalEntry::where('journal_id', $journal->id)->count() + 1;

        $entry = JournalEntry::create([
            'entry_number' => sprintf('%s-%s-%04d', $journal->code, now()->format('Ym'), $sequence),
            'date' => now()->toDateString(),
            'entry_date' => now()->toDateString(),
            'description' => $label,
            'currency' => $po->currency ?? 'MGA',
            'journal_id' => $journal->id,
            'status' => 'posted',
            'posted_at' => now(),
            'created_by' => $userId,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);

        $entry->lines()->create(['account_id' => $counterpartAccount->id, 'description' => $label, 'debit' => $amount, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $treasuryAccount->id, 'description' => $label, 'debit' => 0, 'credit' => $amount]);
    }

    /**
     * Même raison que Modules\Sales\Services\SalesDepositService::generateInvoiceNumber() —
     * acc_invoices.number est NOT NULL et cet appel direct au service ne
     * passe pas par le générateur de InvoiceController::store().
     */
    private function generateInvoiceNumber(string $prefix, string $poNumber): string
    {
        return sprintf('%s-%s-%s', $prefix, $poNumber, now()->format('YmdHis'));
    }

    private function notify(PurchaseOrder $po, string $title, string $body): void
    {
        $owner = $po->created_by ? User::find($po->created_by) : null;
        $this->notifications->notifyProcess(
            array_filter([$owner]),
            $owner,
            $title,
            $body,
            ['module' => 'Achats', 'purchase_order_id' => $po->id],
        );
    }
}
