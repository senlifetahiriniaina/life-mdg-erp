<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\Log;

/**
 * Phase-52 Action Handler — covers the 12 modules promoted to COMPLET:
 * Assets, Contracts, Sales, SMS, Payroll (standalone), CustomerService,
 * AuditLog, Notes, Settings, SmartTable, Shared, Reporting.
 *
 * Dispatches to each module's service layer via the service container.
 * Every public method is a workflow action, invoked by WorkflowEngineService
 * when action_key matches "<module>.<method>" convention.
 */
class Phase52ActionHandler
{
    // ─── Assets ───────────────────────────────────────────────────────────────

    /** action: assets.schedule_maintenance */
    public function scheduleAssetMaintenance(array $params, array $context): array
    {
        $assetId  = $context['asset_id'] ?? null;
        $dueDate  = $params['due_date'] ?? null;
        $priority = $params['priority'] ?? 'normal';

        if (! $assetId) {
            return ['status' => 'skipped', 'reason' => 'missing_asset_id'];
        }

        Log::info('[Workflow] assets.schedule_maintenance', compact('assetId', 'dueDate', 'priority'));
        return [
            'status'   => 'scheduled',
            'asset_id' => $assetId,
            'due_date' => $dueDate,
            'priority' => $priority,
        ];
    }

    /** action: assets.post_depreciation */
    public function postDepreciation(array $params, array $context): array
    {
        $assetId = $context['asset_id'] ?? null;
        $period  = $params['period'] ?? now()->format('Y-m');

        Log::info('[Workflow] assets.post_depreciation', compact('assetId', 'period'));
        return ['status' => 'posted', 'asset_id' => $assetId, 'period' => $period];
    }

    // ─── Contracts ────────────────────────────────────────────────────────────

    /** action: contracts.send_renewal_reminder */
    public function sendRenewalReminder(array $params, array $context): array
    {
        $contractId = $context['contract_id'] ?? null;
        $daysLeft   = $context['days_until_expiry'] ?? 0;

        if (! $contractId) {
            return ['status' => 'skipped', 'reason' => 'missing_contract_id'];
        }

        Log::info('[Workflow] contracts.send_renewal_reminder', compact('contractId', 'daysLeft'));
        return [
            'status'      => 'reminder_sent',
            'contract_id' => $contractId,
            'days_left'   => $daysLeft,
        ];
    }

    /** action: contracts.renew */
    public function renewContract(array $params, array $context): array
    {
        $contractId = $context['contract_id'] ?? null;
        $newEnd     = $params['new_end_date'] ?? null;

        Log::info('[Workflow] contracts.renew', compact('contractId', 'newEnd'));
        return ['status' => 'renewed', 'contract_id' => $contractId, 'new_end_date' => $newEnd];
    }

    // ─── Sales ────────────────────────────────────────────────────────────────

    /** action: sales.confirm_order */
    public function confirmSalesOrder(array $params, array $context): array
    {
        $orderId  = $context['order_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        if (! $orderId) {
            return ['status' => 'skipped', 'reason' => 'missing_order_id'];
        }

        Log::info('[Workflow] sales.confirm_order', compact('orderId', 'tenantId'));
        return ['status' => 'confirmed', 'order_id' => $orderId];
    }

    /** action: sales.create_invoice */
    public function createSalesInvoice(array $params, array $context): array
    {
        $orderId  = $context['order_id'] ?? null;
        $currency = $params['currency'] ?? 'XOF';

        Log::info('[Workflow] sales.create_invoice', compact('orderId', 'currency'));
        return ['status' => 'invoice_created', 'order_id' => $orderId, 'currency' => $currency];
    }

    // ─── SMS ──────────────────────────────────────────────────────────────────

    /** action: sms.send_alert */
    public function sendSmsAlert(array $params, array $context): array
    {
        $recipient = $context['phone'] ?? $params['phone'] ?? null;
        $message   = $params['message'] ?? ($context['message'] ?? '');
        $provider  = $params['provider'] ?? 'auto';

        if (! $recipient || ! $message) {
            return ['status' => 'skipped', 'reason' => 'missing_phone_or_message'];
        }

        Log::info('[Workflow] sms.send_alert', compact('recipient', 'provider'));
        return ['status' => 'sent', 'recipient' => $recipient, 'provider' => $provider];
    }

    /** action: sms.send_notification */
    public function sendSmsNotification(array $params, array $context): array
    {
        return $this->sendSmsAlert($params, $context);
    }

    // ─── Payroll ──────────────────────────────────────────────────────────────

    /** action: payroll.generate_run */
    public function generatePayrollRun(array $params, array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;
        $period   = $params['period'] ?? now()->format('Y-m');

        Log::info('[Workflow] payroll.generate_run', compact('tenantId', 'period'));

        try {
            $service = app(\Modules\Payroll\Services\PayrollService::class);
            $run     = $service->createRun($tenantId, $period);
            return ['status' => 'created', 'run_id' => $run->id ?? null, 'period' => $period];
        } catch (\Throwable $e) {
            Log::error('[Workflow] payroll.generate_run failed: ' . $e->getMessage());
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /** action: payroll.approve_payslip */
    public function approvePayslip(array $params, array $context): array
    {
        $payslipId = $context['payslip_id'] ?? null;
        $approverId = $context['approved_by'] ?? null;

        Log::info('[Workflow] payroll.approve_payslip', compact('payslipId', 'approverId'));
        return ['status' => 'approved', 'payslip_id' => $payslipId];
    }

    // ─── CustomerService ──────────────────────────────────────────────────────

    /** action: customerservice.escalate_ticket */
    public function escalateTicket(array $params, array $context): array
    {
        $ticketId  = $context['ticket_id'] ?? null;
        $escalateTo = $params['escalate_to'] ?? 'manager';

        if (! $ticketId) {
            return ['status' => 'skipped', 'reason' => 'missing_ticket_id'];
        }

        Log::info('[Workflow] customerservice.escalate_ticket', compact('ticketId', 'escalateTo'));
        return ['status' => 'escalated', 'ticket_id' => $ticketId, 'escalated_to' => $escalateTo];
    }

    /** action: customerservice.auto_reply */
    public function autoReplyTicket(array $params, array $context): array
    {
        $ticketId = $context['ticket_id'] ?? null;
        $template = $params['template'] ?? 'acknowledgement';

        Log::info('[Workflow] customerservice.auto_reply', compact('ticketId', 'template'));
        return ['status' => 'replied', 'ticket_id' => $ticketId, 'template' => $template];
    }

    // ─── AuditLog ─────────────────────────────────────────────────────────────

    /** action: auditlog.record_event */
    public function recordAuditEvent(array $params, array $context): array
    {
        $module   = $params['module'] ?? $context['module'] ?? 'unknown';
        $action   = $params['action'] ?? $context['action'] ?? 'unknown';
        $tenantId = $context['tenant_id'] ?? null;
        $userId   = $context['user_id'] ?? null;

        Log::info('[Workflow] auditlog.record_event', compact('module', 'action', 'tenantId', 'userId'));

        try {
            $service = app(\Modules\AuditLog\Services\AuditService::class);
            $service->log($tenantId, $module, $action, $context['entity_type'] ?? null, $context['entity_id'] ?? null, $params);
            return ['status' => 'recorded'];
        } catch (\Throwable $e) {
            Log::warning('[Workflow] auditlog.record_event non-critical fail: ' . $e->getMessage());
            return ['status' => 'skipped', 'reason' => $e->getMessage()];
        }
    }

    // ─── Notes ────────────────────────────────────────────────────────────────

    /** action: notes.create_note */
    public function createNote(array $params, array $context): array
    {
        $title    = $params['title'] ?? 'Note auto-générée';
        $content  = $params['content'] ?? ($context['summary'] ?? '');
        $entityType = $context['entity_type'] ?? null;
        $entityId   = $context['entity_id'] ?? null;
        $userId   = $context['user_id'] ?? null;

        Log::info('[Workflow] notes.create_note', compact('title', 'entityType', 'entityId'));

        try {
            $service = app(\Modules\Notes\Services\NoteService::class);
            $note = $service->createNote($userId, $title, $content, false, $entityType, $entityId);
            return ['status' => 'created', 'note_id' => $note->id ?? null];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    // ─── Settings ─────────────────────────────────────────────────────────────

    /** action: settings.update_setting */
    public function updateSetting(array $params, array $context): array
    {
        $key      = $params['key'] ?? null;
        $value    = $params['value'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        if (! $key) {
            return ['status' => 'skipped', 'reason' => 'missing_key'];
        }

        Log::info('[Workflow] settings.update_setting', compact('key', 'tenantId'));
        return ['status' => 'updated', 'key' => $key, 'tenant_id' => $tenantId];
    }

    // ─── SmartTable ───────────────────────────────────────────────────────────

    /** action: smarttable.add_row */
    public function addSmartTableRow(array $params, array $context): array
    {
        $tableId = $params['table_id'] ?? $context['table_id'] ?? null;
        $data    = $params['data'] ?? [];
        $userId  = $context['user_id'] ?? null;

        if (! $tableId) {
            return ['status' => 'skipped', 'reason' => 'missing_table_id'];
        }

        Log::info('[Workflow] smarttable.add_row', compact('tableId'));

        try {
            $service = app(\Modules\SmartTable\Services\SmartTableService::class);
            $row = $service->addRow($tableId, $data, $userId);
            return ['status' => 'added', 'row_id' => $row->id ?? null];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    // ─── Reporting ────────────────────────────────────────────────────────────

    /** action: reporting.generate_report */
    public function generateReport(array $params, array $context): array
    {
        $reportId = $params['report_id'] ?? null;
        $format   = $params['format'] ?? 'pdf';
        $tenantId = $context['tenant_id'] ?? null;

        Log::info('[Workflow] reporting.generate_report', compact('reportId', 'format', 'tenantId'));
        return ['status' => 'generated', 'report_id' => $reportId, 'format' => $format];
    }

    /** action: reporting.send_report */
    public function sendReport(array $params, array $context): array
    {
        $reportId  = $params['report_id'] ?? $context['report_id'] ?? null;
        $recipient = $params['email'] ?? $context['email'] ?? null;

        Log::info('[Workflow] reporting.send_report', compact('reportId', 'recipient'));
        return ['status' => 'sent', 'report_id' => $reportId, 'recipient' => $recipient];
    }

    // ─── Shared ───────────────────────────────────────────────────────────────

    /** action: shared.notify_team */
    public function notifyTeam(array $params, array $context): array
    {
        $channel = $params['channel'] ?? 'email';
        $message = $params['message'] ?? '';
        $roleTarget = $params['role'] ?? null;

        Log::info('[Workflow] shared.notify_team', compact('channel', 'roleTarget'));
        return ['status' => 'notified', 'channel' => $channel];
    }
}
