<?php

declare(strict_types=1);

namespace Modules\Sales\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Payment;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Contact;
use Modules\Core\Services\ParticipantNotificationService;
use Modules\Sales\Models\SalesOrder;
use Modules\Shared\Models\Currency;

/**
 * Chantier 22 (volet B de la feuille de route Chantier 21) — cycle
 * acompte/solde côté vente : le client paie un acompte (30-50%) sur la base
 * du devis avant lancement de production, puis le solde à la livraison.
 *
 * Réutilise l'infrastructure Accounting déjà réelle plutôt que de la
 * dupliquer : chaque acompte/solde est une vraie Invoice (mêmes règles que
 * InvoiceController::store()/recordPayment(), appelées ici directement en
 * tant que service plutôt que via une requête HTTP interne). Le compte
 * OHADA 419 "Clients créditeurs — Avances reçues" (seedé, jusque-là jamais
 * utilisé par aucun code — voir CLAUDE.md) est enfin activé pour de vrai :
 * un acompte encaissé est une dette envers le client, pas un produit.
 *
 * Traitement comptable volontairement simplifié pour ce volet (documenté,
 * pas un bug) : le solde encaissé est comptabilisé comme une créance client
 * ordinaire (Crédit 411) sans lettrage automatique de l'avance 419 déjà
 * reçue — un rapprochement manuel en fin de période reste nécessaire.
 * Construire ce lettrage automatique est un chantier comptable à part
 * entière, hors du périmètre de "activer le cycle acompte/solde".
 */
class SalesDepositService
{
    public function __construct(private readonly ParticipantNotificationService $notifications) {}

    public function requestDeposit(SalesOrder $order, float $percent, ?int $userId): SalesOrder
    {
        if ($order->deposit_invoice_id !== null) {
            throw new \RuntimeException("Un acompte a déjà été demandé pour la commande {$order->reference}.");
        }
        if ($percent <= 0 || $percent > 100) {
            throw new \RuntimeException('Le pourcentage d\'acompte doit être compris entre 0 et 100.');
        }

        $amount = round((float) $order->total * ($percent / 100), 2);
        $customerName = $this->resolveCustomerName($order);

        $invoice = Invoice::create([
            'number' => $this->generateInvoiceNumber('DEP', $order->reference),
            'type' => 'invoice',
            'partner_type' => 'customer',
            'customer_id' => $order->contact_id ?? $order->account_id ?? 0,
            'partner_name' => $customerName,
            'customer_name' => $customerName,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'sent',
            'currency' => $order->currency ?? 'MGA',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'amount_paid' => 0,
            'amount_due' => $amount,
            'notes' => "Acompte {$percent}% — commande {$order->reference}",
            'created_by' => $userId,
        ]);

        $order->update([
            'deposit_percent' => $percent,
            'deposit_required_amount' => $amount,
            'deposit_invoice_id' => $invoice->id,
        ]);

        return $order->fresh(['depositInvoice']);
    }

    public function requestBalance(SalesOrder $order, ?int $userId): SalesOrder
    {
        if ($order->balance_invoice_id !== null) {
            throw new \RuntimeException("Le solde a déjà été demandé pour la commande {$order->reference}.");
        }

        $depositAmount = (float) ($order->deposit_required_amount ?? 0);
        $amount = round((float) $order->total - $depositAmount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Aucun solde restant à facturer sur cette commande.');
        }

        $customerName = $this->resolveCustomerName($order);

        $invoice = Invoice::create([
            'number' => $this->generateInvoiceNumber('SLD', $order->reference),
            'type' => 'invoice',
            'partner_type' => 'customer',
            'customer_id' => $order->contact_id ?? $order->account_id ?? 0,
            'partner_name' => $customerName,
            'customer_name' => $customerName,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'sent',
            'currency' => $order->currency ?? 'MGA',
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'amount_paid' => 0,
            'amount_due' => $amount,
            'notes' => "Solde — commande {$order->reference}",
            'created_by' => $userId,
        ]);

        $order->update(['balance_invoice_id' => $invoice->id]);

        return $order->fresh(['balanceInvoice']);
    }

    public function recordDepositPayment(SalesOrder $order, float $amount, ?string $method, ?string $reference, ?int $userId): SalesOrder
    {
        if ($order->deposit_invoice_id === null) {
            throw new \RuntimeException('Aucun acompte n\'a été demandé pour cette commande.');
        }

        $invoice = Invoice::findOrFail($order->deposit_invoice_id);

        // Chantier 22 empirical test run: postJournalEntry() used to silently
        // no-op when the chart of accounts wasn't seeded, meaning a payment
        // could be recorded (amount_paid updated, Payment row written) with
        // NO general-ledger entry at all and no error — the exact opposite
        // of this feature's point (activate 419 for real). Now wrapped in
        // one transaction: a missing journal/account fails the whole
        // payment, matching TreasuryImportService's own precedent.
        DB::transaction(function () use ($invoice, $amount, $method, $reference, $userId, $order) {
            $this->applyPayment($invoice, $amount, $method, $reference, $userId);
            $this->postJournalEntry($order, $amount, 'sale_deposit', $userId);
        });

        $this->notify($order, 'Acompte encaissé', "Un acompte de {$amount} " . ($order->currency ?? 'MGA') . " a été encaissé pour la commande {$order->reference}.");

        return $order->fresh(['depositInvoice', 'balanceInvoice']);
    }

    public function recordBalancePayment(SalesOrder $order, float $amount, ?string $method, ?string $reference, ?int $userId): SalesOrder
    {
        if ($order->balance_invoice_id === null) {
            throw new \RuntimeException('Aucun solde n\'a été demandé pour cette commande.');
        }

        $invoice = Invoice::findOrFail($order->balance_invoice_id);

        DB::transaction(function () use ($invoice, $amount, $method, $reference, $userId, $order) {
            $this->applyPayment($invoice, $amount, $method, $reference, $userId);
            $this->postJournalEntry($order, $amount, 'sale_balance', $userId);
        });

        $this->notify($order, 'Solde encaissé', "Le solde de {$amount} " . ($order->currency ?? 'MGA') . " a été encaissé pour la commande {$order->reference}.");

        return $order->fresh(['depositInvoice', 'balanceInvoice']);
    }

    /**
     * Même accumulation que Modules\Accounting\Http\Controllers\Api\InvoiceController::recordPayment()
     * (réel, déjà testé) — dupliquée ici plutôt que ré-appelée en HTTP interne,
     * puisqu'un appel de service à service est plus direct qu'une requête
     * HTTP simulée pour un même processus métier.
     */
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
     * Débit trésorerie (512 Banque par défaut) / Crédit 419 pour un
     * acompte encaissé, ou Crédit 411 pour un solde — voir le docblock de
     * classe pour la simplification assumée (pas de lettrage 419→411).
     *
     * Échoue fort (RuntimeException) plutôt que silencieusement si le
     * journal VTE ou les comptes 512/419/411 ne sont pas seedés — un
     * paiement "réussi" sans écriture comptable serait pire qu'un échec
     * explicite (voir le commentaire dans recordDepositPayment()/
     * recordBalancePayment() ci-dessus, corrigé après un vrai test Pest
     * l'ayant démontré : compte non seedé → aucune écriture, aucune erreur).
     * Appelée à l'intérieur du DB::transaction() du site d'appel, donc un
     * échec ici annule aussi la mise à jour de la facture/le Payment.
     */
    private function postJournalEntry(SalesOrder $order, float $amount, string $kind, ?int $userId): void
    {
        $journal = Journal::where('code', 'VTE')->first();
        $treasuryAccount = ChartOfAccount::where('code', '512')->first();
        $counterpartCode = $kind === 'sale_deposit' ? '419' : '411';
        $counterpartAccount = ChartOfAccount::where('code', $counterpartCode)->first();

        if ($journal === null || $treasuryAccount === null || $counterpartAccount === null) {
            throw new \RuntimeException('Plan comptable incomplet : journal VTE ou compte 512/419/411 introuvable.');
        }

        $label = $kind === 'sale_deposit'
            ? "Acompte reçu — commande {$order->reference}"
            : "Solde reçu — commande {$order->reference}";

        // Chantier 32 (volet B) — le grand livre est toujours en MGA
        // (même convention que FinancialSimulationService/BudgetGenerationService
        // ailleurs dans l'app) ; une commande dans une autre devise (EUR/
        // USD/CNY) postait jusqu'ici son montant brut tel quel, corrompant
        // silencieusement les états financiers consolidés dès qu'une
        // commande non-MGA existait — confirmé empiriquement avant ce
        // correctif. La devise déclarée de la facture (Invoice.currency)
        // n'est pas modifiée, seule l'écriture de journal l'est.
        $postedAmount = $this->convertToMga($amount, $order->currency ?? 'MGA');

        $sequence = JournalEntry::where('journal_id', $journal->id)->count() + 1;

        $entry = JournalEntry::create([
            'entry_number' => sprintf('%s-%s-%04d', $journal->code, now()->format('Ym'), $sequence),
            'date' => now()->toDateString(),
            'entry_date' => now()->toDateString(),
            'description' => $label,
            'currency' => 'MGA',
            'journal_id' => $journal->id,
            'status' => 'posted',
            'posted_at' => now(),
            'created_by' => $userId,
            'reference_type' => SalesOrder::class,
            'reference_id' => $order->id,
        ]);

        $entry->lines()->create(['account_id' => $treasuryAccount->id, 'description' => $label, 'debit' => $postedAmount, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $counterpartAccount->id, 'description' => $label, 'debit' => 0, 'credit' => $postedAmount]);
    }

    /**
     * Convertit un montant vers MGA en réutilisant les taux réels déjà
     * seedés (shared_currencies, Chantier 17) — même formule que
     * SourcingBenchmarkService::convert()/CostingSheetService, dupliquée
     * plutôt que partagée suivant le précédent déjà établi dans ce
     * dépôt pour cette logique transverse légère. Repli fallback-first :
     * si un taux réel manque, le montant brut est posté tel quel plutôt
     * que de bloquer l'écriture — mieux vaut une écriture non convertie
     * mais réellement postée qu'aucune écriture du tout.
     */
    private function convertToMga(float $amount, string $from): float
    {
        $from = strtoupper($from);
        if ($from === 'MGA') {
            return round($amount, 2);
        }

        $fromCurrency = Currency::where('code', $from)->first();
        $toCurrency = Currency::where('code', 'MGA')->first();

        if ($fromCurrency === null || $toCurrency === null
            || $fromCurrency->exchange_rate_to_usd === null || $toCurrency->exchange_rate_to_usd === null) {
            return round($amount, 2);
        }

        $amountInUsd = $amount / (float) $fromCurrency->exchange_rate_to_usd;

        return round($amountInUsd * (float) $toCurrency->exchange_rate_to_usd, 2);
    }

    /**
     * Invoice::create() ne passe pas par StoreInvoiceRequest/InvoiceController::store()
     * (appel de service direct, pas de requête HTTP) — le numéro
     * auto-généré de store() n'est donc jamais exécuté ici, et acc_invoices.number
     * est NOT NULL (confirmé empiriquement : premier essai réel a échoué sur
     * cette contrainte). Préfixe+référence commande+horodatage : lisible,
     * pas de collision réaliste dans ce flux synchrone.
     */
    private function generateInvoiceNumber(string $prefix, string $orderReference): string
    {
        return sprintf('%s-%s-%s', $prefix, $orderReference, now()->format('YmdHis'));
    }

    private function resolveCustomerName(SalesOrder $order): string
    {
        if ($order->account_id !== null) {
            $account = Account::find($order->account_id);
            if ($account !== null && $account->name) {
                return $account->name;
            }
        }

        if ($order->contact_id !== null) {
            $contact = Contact::find($order->contact_id);
            if ($contact !== null) {
                $name = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return 'Client';
    }

    private function notify(SalesOrder $order, string $title, string $body): void
    {
        $owner = $order->created_by ? User::find($order->created_by) : null;
        $this->notifications->notifyProcess(
            array_filter([$owner]),
            $owner,
            $title,
            $body,
            ['module' => 'Sales', 'sales_order_id' => $order->id],
        );
    }
}
