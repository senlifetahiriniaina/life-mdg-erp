<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DocumentsActionHandler — Phase 39
 *
 * Handles workflow actions for the Documents module:
 * PDF generation, email dispatch, e-signature, archiving, expiry alerts.
 *
 * Supported templates: invoice, quote, purchase_order, payslip, contract, delivery_note
 */
class DocumentsActionHandler
{
    /** @var array<string> */
    private const SUPPORTED_TEMPLATES = [
        'invoice',
        'quote',
        'purchase_order',
        'payslip',
        'contract',
        'delivery_note',
    ];

    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'documents.generate_pdf'        => $this->generatePdf($params, $context),
            'documents.send_by_email'       => $this->sendByEmail($params, $context),
            'documents.request_signature'   => $this->requestSignature($params, $context),
            'documents.archive'             => $this->archive($params, $context),
            'documents.notify_expiry'       => $this->notifyExpiry($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Documents action: {$action}"],
        };
    }

    /**
     * action: documents.generate_pdf
     * Generate a PDF from a named template populated with context data.
     *
     * @param  array<string,mixed>  $params   e.g. ['template' => 'invoice']
     * @param  array<string,mixed>  $context
     * @return array{document_id: int|null, template: string, status: string}
     */
    public function generatePdf(array $params, array $context): array
    {
        $tenantId   = $context['tenant_id'] ?? 1;
        $template   = $params['template'] ?? 'invoice';
        $relatedId  = $context['invoice_id'] ?? $context['order_id'] ?? $context['quote_id'] ?? null;
        $relatedType = $context['document_type'] ?? $template;

        if (! in_array($template, self::SUPPORTED_TEMPLATES, true)) {
            return [
                'status'    => 'error',
                'reason'    => "Unsupported template '{$template}'. Supported: " . implode(', ', self::SUPPORTED_TEMPLATES),
            ];
        }

        try {
            $documentId = DB::table('documents')->insertGetId([
                'tenant_id'    => $tenantId,
                'template'     => $template,
                'related_id'   => $relatedId,
                'related_type' => $relatedType,
                'status'       => 'generated',
                'source'       => 'workflow_automation',
                'generated_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            Log::info('WorkflowAction: PDF generated', ['document_id' => $documentId, 'template' => $template]);

            return ['document_id' => $documentId, 'template' => $template, 'status' => 'generated'];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: generatePdf skipped', ['error' => $e->getMessage()]);
            return ['document_id' => null, 'template' => $template, 'status' => 'simulated'];
        }
    }

    /**
     * action: documents.send_by_email
     * Send a previously generated document by email.
     *
     * @param  array<string,mixed>  $params   e.g. ['to_email' => 'client@example.com', 'subject' => '...']
     * @param  array<string,mixed>  $context
     * @return array{email_sent: bool, to_email: string|null}
     */
    public function sendByEmail(array $params, array $context): array
    {
        $documentId = $context['document_id'] ?? null;
        $tenantId   = $context['tenant_id'] ?? 1;
        $toEmail    = $params['to_email'] ?? ($context['client_email'] ?? null);
        $subject    = $params['subject'] ?? 'Document depuis WideHalo ERP';

        if (! $documentId) {
            return ['status' => 'error', 'reason' => 'Missing document_id in context'];
        }

        try {
            DB::table('email_queue')->insert([
                'tenant_id'   => $tenantId,
                'document_id' => $documentId,
                'to_email'    => $toEmail,
                'subject'     => $subject,
                'status'      => 'queued',
                'source'      => 'workflow_automation',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: sendByEmail skipped', ['error' => $e->getMessage()]);
            return ['email_sent' => false, 'to_email' => $toEmail, 'status' => 'simulated'];
        }

        return ['email_sent' => true, 'to_email' => $toEmail, 'document_id' => $documentId];
    }

    /**
     * action: documents.request_signature
     * Trigger an e-signature workflow for a document.
     *
     * @param  array<string,mixed>  $params   e.g. ['signers' => ['email1@x.com', 'email2@x.com']]
     * @param  array<string,mixed>  $context
     * @return array{signature_request_id: int|null, status: string}
     */
    public function requestSignature(array $params, array $context): array
    {
        $documentId = $context['document_id'] ?? null;
        $tenantId   = $context['tenant_id'] ?? 1;
        $signers    = $params['signers'] ?? [];
        $dueDate    = $params['due_date'] ?? now()->addDays(7)->toDateString();

        if (! $documentId) {
            return ['status' => 'error', 'reason' => 'Missing document_id in context'];
        }

        try {
            $requestId = DB::table('document_signature_requests')->insertGetId([
                'tenant_id'   => $tenantId,
                'document_id' => $documentId,
                'signers'     => json_encode($signers),
                'due_date'    => $dueDate,
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return ['signature_request_id' => $requestId, 'status' => 'pending', 'signers_count' => count($signers)];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: requestSignature skipped', ['error' => $e->getMessage()]);
            return ['signature_request_id' => null, 'status' => 'simulated', 'signers_count' => count($signers)];
        }
    }

    /**
     * action: documents.archive
     * Move a document to the archive folder.
     *
     * @param  array<string,mixed>  $params   e.g. ['archive_folder' => 'contracts_2026']
     * @param  array<string,mixed>  $context
     * @return array{archived: bool, document_id: int|null}
     */
    public function archive(array $params, array $context): array
    {
        $documentId    = $context['document_id'] ?? null;
        $tenantId      = $context['tenant_id'] ?? 1;
        $archiveFolder = $params['archive_folder'] ?? 'archived';

        if (! $documentId) {
            return ['status' => 'error', 'reason' => 'Missing document_id in context'];
        }

        try {
            $rows = DB::table('documents')
                ->where('id', $documentId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status'         => 'archived',
                    'archive_folder' => $archiveFolder,
                    'archived_at'    => now(),
                    'updated_at'     => now(),
                ]);

            return ['archived' => $rows > 0, 'document_id' => $documentId, 'archive_folder' => $archiveFolder];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: archive skipped', ['error' => $e->getMessage()]);
            return ['archived' => false, 'document_id' => $documentId, 'status' => 'simulated'];
        }
    }

    /**
     * action: documents.notify_expiry
     * Send a notification when a document is approaching its expiry date.
     *
     * @param  array<string,mixed>  $params   e.g. ['days_before' => 30]
     * @param  array<string,mixed>  $context
     * @return array{notified: bool}
     */
    public function notifyExpiry(array $params, array $context): array
    {
        $documentId = $context['document_id'] ?? null;
        $tenantId   = $context['tenant_id'] ?? 1;
        $daysBefore = (int) ($params['days_before'] ?? 30);
        $expiryDate = $context['expiry_date'] ?? $params['expiry_date'] ?? null;

        try {
            DB::table('notifications')->insert([
                'tenant_id'       => $tenantId,
                'notifiable_type' => 'document',
                'notifiable_id'   => (int) $documentId,
                'type'            => 'documents.expiry_alert',
                'data'            => json_encode([
                    'document_id' => $documentId,
                    'expiry_date' => $expiryDate,
                    'days_before' => $daysBefore,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return ['notified' => true, 'days_before' => $daysBefore, 'expiry_date' => $expiryDate];
    }
}
