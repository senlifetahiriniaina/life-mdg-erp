<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action handler for Inventory → Accounting workflow steps.
 *
 * OHADA account classes used:
 *   Cl.2 — Immobilisations
 *   Cl.3 — Stocks
 *   Cl.4 — Tiers (Fournisseurs = 401)
 *   Cl.5 — Trésorerie
 *   Cl.6 — Charges (Achats = 601-609)
 *   Cl.7 — Produits
 *
 * Approval thresholds (OHADA/UEMOA):
 *   > 100 000 XOF → 1 niveau d'approbation
 *   > 500 000 XOF → 3 niveaux d'approbation
 *
 * Supported action keys:
 *   - accounting.book_purchase_invoice
 *   - accounting.book_stock_variation
 *   - accounting.generate_payment_schedule
 *   - accounting.flag_for_approval
 */
class InventoryAccountingActionHandler
{
    /**
     * OHADA default account map for purchase bookings.
     *
     * Key  = product category / expense type
     * Value = [debit_account, credit_account, label]
     */
    private const OHADA_PURCHASE_ACCOUNTS = [
        'merchandise'   => ['601', '401', 'Achats de marchandises'],
        'raw_material'  => ['602', '401', 'Achats de matières premières'],
        'supplies'      => ['604', '401', 'Achats de fournitures consommables'],
        'services'      => ['624', '401', 'Prestations de services extérieures'],
        'equipment'     => ['241', '401', 'Immobilisations corporelles — Matériel'],
        'default'       => ['601', '401', 'Achats'],
    ];

    /** OHADA approval thresholds (XOF) */
    private const THRESHOLD_SINGLE   = 100_000;
    private const THRESHOLD_MULTI    = 500_000;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatch an action method by snake_case key.
     *
     * Called by WorkflowEngineService::executeAction() when the prefix is
     * 'accounting'.
     *
     * @param  string               $method   e.g. 'book_purchase_invoice'
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $method, array $params, array $context): array
    {
        return match ($method) {
            'book_purchase_invoice'   => $this->bookPurchaseInvoice($params, $context),
            'book_stock_variation'    => $this->bookStockVariation($params, $context),
            'generate_payment_schedule' => $this->generatePaymentSchedule($params, $context),
            'flag_for_approval'       => $this->flagForApproval($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Accounting action: {$method}"],
        };
    }

    /**
     * action: accounting.book_purchase_invoice
     *
     * Records an OHADA-compliant double-entry journal entry when a purchase
     * invoice is received from a supplier.
     *
     *   Débit  : Cl.6 Charges (ex. 601 Achats de marchandises)
     *   Crédit : Cl.4 Fournisseurs (401)
     *
     * Required context keys:
     *   - invoice_id    (int)
     *   - amount        (float)   Invoice total (excl. taxes)
     *   - currency      (string)  Defaults to 'XOF'
     *   - supplier_id   (int)
     *
     * Optional context keys:
     *   - ohada_account   (string)  Override debit account (e.g. '602')
     *   - expense_type    (string)  merchandise|raw_material|supplies|services|equipment
     *   - tax_amount      (float)   VAT/TVA amount
     *   - invoice_date    (string)  ISO 8601
     *   - reference       (string)  Supplier invoice reference
     *   - tenant_id       (int)
     */
    public function bookPurchaseInvoice(array $params, array $context): array
    {
        $invoiceId   = $context['invoice_id']  ?? null;
        $amount      = (float) ($context['amount'] ?? 0);
        $currency    = $context['currency']    ?? 'XOF';
        $supplierId  = $context['supplier_id'] ?? null;
        $tenantId    = $context['tenant_id']   ?? 1;

        if (!$invoiceId || $amount <= 0 || !$supplierId) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : invoice_id, amount et supplier_id sont requis.',
            ];
        }

        // Determine OHADA accounts
        $expenseType    = $context['expense_type'] ?? 'default';
        $accountConfig  = self::OHADA_PURCHASE_ACCOUNTS[$expenseType]
                          ?? self::OHADA_PURCHASE_ACCOUNTS['default'];

        $debitAccount   = $context['ohada_account'] ?? $accountConfig[0];
        $creditAccount  = $accountConfig[1]; // always 401 — Fournisseurs
        $entryLabel     = $accountConfig[2];

        $taxAmount      = (float) ($context['tax_amount']   ?? 0);
        $invoiceDate    = $context['invoice_date'] ?? now()->toDateString();
        $reference      = $context['reference']   ?? "FAC-{$invoiceId}";

        try {
            $journalEntryId = DB::table('accounting_journal_entries')->insertGetId([
                'tenant_id'     => $tenantId,
                'entry_date'    => $invoiceDate,
                'reference'     => $reference,
                'description'   => "Facture fournisseur #{$invoiceId} — {$entryLabel}",
                'currency'      => $currency,
                'total_debit'   => $amount + $taxAmount,
                'total_credit'  => $amount + $taxAmount,
                'source_type'   => 'purchase_invoice',
                'source_id'     => $invoiceId,
                'status'        => 'posted',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Debit line: Cl.6 Charges
            DB::table('accounting_journal_lines')->insert([
                'journal_entry_id' => $journalEntryId,
                'account_code'     => $debitAccount,
                'account_class'    => 'Cl.6',
                'label'            => $entryLabel,
                'debit'            => $amount,
                'credit'           => 0,
                'currency'         => $currency,
                'supplier_id'      => $supplierId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // TVA recoverable (if applicable)
            if ($taxAmount > 0) {
                DB::table('accounting_journal_lines')->insert([
                    'journal_entry_id' => $journalEntryId,
                    'account_code'     => '4456',
                    'account_class'    => 'Cl.4',
                    'label'            => 'TVA déductible sur achats',
                    'debit'            => $taxAmount,
                    'credit'           => 0,
                    'currency'         => $currency,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            // Credit line: Cl.4 Fournisseurs (401)
            DB::table('accounting_journal_lines')->insert([
                'journal_entry_id' => $journalEntryId,
                'account_code'     => $creditAccount,
                'account_class'    => 'Cl.4',
                'label'            => "Fournisseur #{$supplierId}",
                'debit'            => 0,
                'credit'           => $amount + $taxAmount,
                'currency'         => $currency,
                'supplier_id'      => $supplierId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Update invoice status to 'booked'
            DB::table('achats_invoices')
                ->where('id', $invoiceId)
                ->update([
                    'accounting_status'  => 'booked',
                    'journal_entry_id'   => $journalEntryId,
                    'updated_at'         => now(),
                ]);

            return [
                'status'          => 'success',
                'action'          => 'accounting.book_purchase_invoice',
                'journal_entry_id' => $journalEntryId,
                'invoice_id'      => $invoiceId,
                'debit_account'   => $debitAccount,
                'credit_account'  => $creditAccount,
                'amount'          => $amount,
                'tax_amount'      => $taxAmount,
                'currency'        => $currency,
                'message'         => "Écriture comptable OHADA enregistrée (débit {$debitAccount} / crédit {$creditAccount}) pour la facture #{$invoiceId}.",
            ];
        } catch (\Throwable $e) {
            Log::error('InventoryAccountingActionHandler::bookPurchaseInvoice error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la comptabilisation : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: accounting.book_stock_variation
     *
     * Records stock valuation change in the general ledger when a purchase
     * receipt updates inventory levels.
     *
     *   Débit  : Cl.3 Stocks (31x — Marchandises / matières)
     *   Crédit : Cl.6 Variation de stocks (603x)
     *
     * Required context keys:
     *   - po_id      (int)
     *   - amount     (float)  Total stock value delta (quantity × unit_cost)
     *   - currency   (string) Defaults to 'XOF'
     *
     * Optional context keys:
     *   - stock_account       (string) Defaults to '31' — Marchandises
     *   - variation_account   (string) Defaults to '6031'
     *   - invoice_date        (string) ISO 8601
     *   - warehouse_id        (int)
     *   - tenant_id           (int)
     */
    public function bookStockVariation(array $params, array $context): array
    {
        $poId      = $context['po_id']    ?? null;
        $amount    = (float) ($context['amount'] ?? 0);
        $currency  = $context['currency'] ?? 'XOF';
        $tenantId  = $context['tenant_id'] ?? 1;

        // Derive amount from stock_entries if not provided directly
        if ($amount <= 0 && !empty($context['stock_entries'])) {
            foreach ($context['stock_entries'] as $entry) {
                $amount += (float) ($entry['quantity'] ?? 0) * (float) ($entry['unit_cost'] ?? 0);
            }
        }

        if (!$poId || $amount <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : po_id et amount (ou stock_entries) sont requis.',
            ];
        }

        $stockAccount     = $context['stock_account']     ?? '31';
        $variationAccount = $context['variation_account'] ?? '6031';
        $entryDate        = $context['invoice_date']      ?? now()->toDateString();
        $reference        = "PO-{$poId}";

        try {
            $journalEntryId = DB::table('accounting_journal_entries')->insertGetId([
                'tenant_id'    => $tenantId,
                'entry_date'   => $entryDate,
                'reference'    => $reference,
                'description'  => "Entrée en stock — BC #{$poId}",
                'currency'     => $currency,
                'total_debit'  => $amount,
                'total_credit' => $amount,
                'source_type'  => 'purchase_order',
                'source_id'    => $poId,
                'status'       => 'posted',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Debit: Cl.3 Stocks
            DB::table('accounting_journal_lines')->insert([
                'journal_entry_id' => $journalEntryId,
                'account_code'     => $stockAccount,
                'account_class'    => 'Cl.3',
                'label'            => 'Entrée en stock (réception BC)',
                'debit'            => $amount,
                'credit'           => 0,
                'currency'         => $currency,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Credit: Cl.6 Variation de stocks
            DB::table('accounting_journal_lines')->insert([
                'journal_entry_id' => $journalEntryId,
                'account_code'     => $variationAccount,
                'account_class'    => 'Cl.6',
                'label'            => 'Variation de stocks sur achats',
                'debit'            => 0,
                'credit'           => $amount,
                'currency'         => $currency,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return [
                'status'           => 'success',
                'action'           => 'accounting.book_stock_variation',
                'journal_entry_id' => $journalEntryId,
                'po_id'            => $poId,
                'stock_account'    => $stockAccount,
                'variation_account' => $variationAccount,
                'amount'           => $amount,
                'currency'         => $currency,
                'message'          => "Variation de stock OHADA comptabilisée (débit {$stockAccount} / crédit {$variationAccount}) : {$amount} {$currency}.",
            ];
        } catch (\Throwable $e) {
            Log::error('InventoryAccountingActionHandler::bookStockVariation error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la comptabilisation de variation de stock : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: accounting.generate_payment_schedule
     *
     * Creates an installment payment schedule for large invoices, splitting
     * the total across multiple due dates.
     *
     * Required context keys:
     *   - invoice_id   (int)
     *   - amount       (float)
     *   - currency     (string) Defaults to 'XOF'
     *
     * Optional context keys:
     *   - installments  (int)    Number of installments — defaults to 3
     *   - interval_days (int)    Days between installments — defaults to 30
     *   - start_date    (string) ISO 8601 date of first payment — defaults to today+30d
     *   - supplier_id   (int)
     *   - tenant_id     (int)
     */
    public function generatePaymentSchedule(array $params, array $context): array
    {
        $invoiceId    = $context['invoice_id']    ?? null;
        $amount       = (float) ($context['amount'] ?? 0);
        $currency     = $context['currency']      ?? 'XOF';
        $tenantId     = $context['tenant_id']     ?? 1;
        $supplierId   = $context['supplier_id']   ?? null;
        $installments = (int) ($params['installments']  ?? $context['installments']  ?? 3);
        $intervalDays = (int) ($params['interval_days'] ?? $context['interval_days'] ?? 30);
        $startDate    = isset($context['start_date'])
            ? \Carbon\Carbon::parse($context['start_date'])
            : now()->addDays($intervalDays);

        if (!$invoiceId || $amount <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : invoice_id et amount sont requis.',
            ];
        }

        $installmentAmount = round($amount / $installments, 2);
        $remainder         = round($amount - ($installmentAmount * ($installments - 1)), 2);

        $schedule = [];

        try {
            for ($i = 1; $i <= $installments; $i++) {
                $dueDate     = $startDate->copy()->addDays(($i - 1) * $intervalDays);
                $paymentAmt  = ($i === $installments) ? $remainder : $installmentAmount;

                $scheduleId = DB::table('accounting_payment_schedules')->insertGetId([
                    'tenant_id'   => $tenantId,
                    'invoice_id'  => $invoiceId,
                    'supplier_id' => $supplierId,
                    'installment' => $i,
                    'total'       => $installments,
                    'amount'      => $paymentAmt,
                    'currency'    => $currency,
                    'due_date'    => $dueDate->toDateString(),
                    'status'      => 'pending',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $schedule[] = [
                    'schedule_id' => $scheduleId,
                    'installment' => $i,
                    'amount'      => $paymentAmt,
                    'due_date'    => $dueDate->toDateString(),
                ];
            }

            // Mark invoice as having a payment schedule
            DB::table('achats_invoices')
                ->where('id', $invoiceId)
                ->update([
                    'payment_schedule_status' => 'scheduled',
                    'updated_at'              => now(),
                ]);

            return [
                'status'     => 'success',
                'action'     => 'accounting.generate_payment_schedule',
                'invoice_id' => $invoiceId,
                'amount'     => $amount,
                'currency'   => $currency,
                'schedule'   => $schedule,
                'message'    => "Échéancier de {$installments} versement(s) créé pour la facture #{$invoiceId} ({$amount} {$currency}).",
            ];
        } catch (\Throwable $e) {
            Log::error('InventoryAccountingActionHandler::generatePaymentSchedule error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la création de l'échéancier : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: accounting.flag_for_approval
     *
     * Flags a purchase invoice for approval based on OHADA/UEMOA thresholds:
     *   > 100 000 XOF → 1 niveau (manager direct)
     *   > 500 000 XOF → 3 niveaux (manager → DAF → DG)
     *
     * Required context keys:
     *   - invoice_id   (int)
     *   - amount       (float)
     *   - currency     (string) Defaults to 'XOF' — non-XOF amounts are not auto-thresholded
     *
     * Optional context keys:
     *   - supplier_id  (int)
     *   - tenant_id    (int)
     *   - approvers    (array) Override approver role list
     */
    public function flagForApproval(array $params, array $context): array
    {
        $invoiceId  = $context['invoice_id']  ?? null;
        $amount     = (float) ($context['amount'] ?? 0);
        $currency   = $context['currency']    ?? 'XOF';
        $tenantId   = $context['tenant_id']   ?? 1;
        $supplierId = $context['supplier_id'] ?? null;

        if (!$invoiceId || $amount <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : invoice_id et amount sont requis.',
            ];
        }

        // Determine approval levels (XOF thresholds only; other currencies always use 1 level)
        if ($currency === 'XOF' && $amount > self::THRESHOLD_MULTI) {
            $levels    = 3;
            $approvers = $params['approvers'] ?? ['manager', 'daf', 'dg'];
            $reason    = sprintf('Montant %.0f XOF > seuil OHADA de %s XOF — approbation 3 niveaux requise.', $amount, number_format(self::THRESHOLD_MULTI, 0, '.', ' '));
        } elseif ($currency === 'XOF' && $amount > self::THRESHOLD_SINGLE) {
            $levels    = 1;
            $approvers = $params['approvers'] ?? ['manager'];
            $reason    = sprintf('Montant %.0f XOF > seuil de %s XOF — approbation 1 niveau requise.', $amount, number_format(self::THRESHOLD_SINGLE, 0, '.', ' '));
        } else {
            $levels    = 1;
            $approvers = $params['approvers'] ?? ['manager'];
            $reason    = 'Approbation standard requise.';
        }

        try {
            $approvalId = DB::table('accounting_invoice_approvals')->insertGetId([
                'tenant_id'          => $tenantId,
                'invoice_id'         => $invoiceId,
                'supplier_id'        => $supplierId,
                'amount'             => $amount,
                'currency'           => $currency,
                'approval_levels'    => $levels,
                'approvers'          => json_encode($approvers),
                'current_level'      => 1,
                'status'             => 'pending',
                'reason'             => $reason,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Block invoice processing until approved
            DB::table('achats_invoices')
                ->where('id', $invoiceId)
                ->update([
                    'approval_status' => 'pending',
                    'approval_id'     => $approvalId,
                    'updated_at'      => now(),
                ]);

            return [
                'status'      => 'success',
                'action'      => 'accounting.flag_for_approval',
                'approval_id' => $approvalId,
                'invoice_id'  => $invoiceId,
                'levels'      => $levels,
                'approvers'   => $approvers,
                'reason'      => $reason,
                'message'     => "Facture #{$invoiceId} ({$amount} {$currency}) soumise à approbation {$levels} niveau(x) : " . implode(' → ', $approvers) . '.',
            ];
        } catch (\Throwable $e) {
            Log::error('InventoryAccountingActionHandler::flagForApproval error', [
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la mise en approbation : {$e->getMessage()}",
            ];
        }
    }
}
