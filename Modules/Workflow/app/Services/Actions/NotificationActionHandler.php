<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Action handler for notification and approval workflow steps.
 *
 * Supported action keys:
 *   - notify.email
 *   - notify.in_app
 *   - notify.sms          (Africa First — mobile operators)
 *   - approval.request_multi_level
 */
class NotificationActionHandler
{
    /**
     * Dispatch an action method by snake_case key.
     *
     * Called by WorkflowEngineService::executeAction() when the prefix is
     * 'notify' or 'approval'.
     *
     * @param  string               $method   e.g. 'email', 'in_app', 'sms', 'request_multi_level'
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $method, array $params, array $context): array
    {
        return match ($method) {
            'email'               => $this->sendEmail($params, $context),
            'in_app'              => $this->sendInAppNotification($params, $context),
            'sms'                 => $this->sendSms($params, $context),
            'request_multi_level' => $this->requestMultiLevelApproval($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Notification/Approval action: {$method}"],
        };
    }

    /**
     * action: notify.email
     *
     * Sends a templated email notification to one or more recipients.
     * Recipients can be specified by role name (resolved to users) or
     * by explicit email address.
     *
     * Required params:
     *   - subject_template  (string)  May contain {{variable}} placeholders
     *   - body_template     (string)
     *
     * Required context keys (used for placeholder substitution):
     *   - to        (string|array) Role name(s) or explicit email address(es)
     *   - tenant_id (int)
     *
     * Optional params:
     *   - from      (string)  Sender address — defaults to app mail from
     *   - cc        (array)
     *   - locale    (string)  Defaults to 'fr'
     */
    public function sendEmail(array $params, array $context): array
    {
        $to              = $context['to']               ?? $params['to']              ?? null;
        $subjectTemplate = $params['subject_template']  ?? $params['subject']         ?? 'Notification WideHalo ERP';
        $bodyTemplate    = $params['body_template']     ?? $params['body']            ?? '';
        $tenantId        = $context['tenant_id']        ?? 1;
        $locale          = $params['locale']            ?? $context['locale']         ?? 'fr';

        if (!$to) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : to est requis.',
            ];
        }

        // Resolve role names to email addresses
        $recipients = $this->resolveRecipients((array) $to, $tenantId);

        if (empty($recipients)) {
            return [
                'status'  => 'skipped',
                'action'  => 'notify.email',
                'message' => "Aucun destinataire résolu pour : " . implode(', ', (array) $to),
            ];
        }

        // Substitute placeholders in subject & body
        $subject = $this->interpolate($subjectTemplate, $context);
        $body    = $this->interpolate($bodyTemplate, $context);

        try {
            foreach ($recipients as $email) {
                Mail::raw($body, function ($message) use ($email, $subject, $params) {
                    $message->to($email)->subject($subject);
                    if (!empty($params['cc'])) {
                        $message->cc((array) $params['cc']);
                    }
                });
            }

            // Log notification
            DB::table('workflow_notifications')->insert([
                'tenant_id'  => $tenantId,
                'channel'    => 'email',
                'recipients' => json_encode($recipients),
                'subject'    => $subject,
                'body'       => mb_substr($body, 0, 1000),
                'status'     => 'sent',
                'sent_at'    => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'status'     => 'success',
                'action'     => 'notify.email',
                'recipients' => $recipients,
                'subject'    => $subject,
                'message'    => sprintf('%d e-mail(s) envoyé(s).', count($recipients)),
            ];
        } catch (\Throwable $e) {
            Log::error('NotificationActionHandler::sendEmail error', [
                'params'    => $params,
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de l'envoi e-mail : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: notify.in_app
     *
     * Creates an in-app notification record for one or more users.
     *
     * Required params:
     *   - title_template   (string)
     *   - body_template    (string)
     *
     * Required context keys:
     *   - to        (string|array) User ID(s) or role name(s)
     *   - tenant_id (int)
     *
     * Optional params:
     *   - type      (string)  info|success|warning|error — default 'info'
     *   - action_url (string) Deep-link URL in the SPA
     *   - module    (string)  Source module (e.g. 'Achats')
     */
    public function sendInAppNotification(array $params, array $context): array
    {
        $to          = $context['to']        ?? $params['to']        ?? null;
        $title       = $this->interpolate($params['title_template'] ?? $params['title'] ?? 'Notification', $context);
        $body        = $this->interpolate($params['body_template']  ?? $params['body']  ?? '', $context);
        $tenantId    = $context['tenant_id'] ?? 1;
        $type        = $params['type']       ?? 'info';
        $actionUrl   = $params['action_url'] ?? null;
        $module      = $params['module']     ?? null;

        if (!$to) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : to est requis.',
            ];
        }

        $userIds = $this->resolveUserIds((array) $to, $tenantId);

        if (empty($userIds)) {
            return [
                'status'  => 'skipped',
                'action'  => 'notify.in_app',
                'message' => "Aucun utilisateur résolu pour : " . implode(', ', (array) $to),
            ];
        }

        try {
            $notificationIds = [];

            foreach ($userIds as $userId) {
                $notifId = DB::table('notifications')->insertGetId([
                    'tenant_id'   => $tenantId,
                    'user_id'     => $userId,
                    'type'        => $type,
                    'module'      => $module,
                    'title'       => $title,
                    'body'        => $body,
                    'action_url'  => $actionUrl,
                    'is_read'     => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $notificationIds[] = $notifId;
            }

            return [
                'status'           => 'success',
                'action'           => 'notify.in_app',
                'notification_ids' => $notificationIds,
                'user_count'       => count($userIds),
                'message'          => sprintf('%d notification(s) in-app créée(s).', count($notificationIds)),
            ];
        } catch (\Throwable $e) {
            Log::error('NotificationActionHandler::sendInAppNotification error', [
                'params'    => $params,
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la création de la notification in-app : {$e->getMessage()}",
            ];
        }
    }

    /**
     * action: notify.sms
     *
     * Africa First — sends SMS via local telecom operators (Orange, MTN,
     * Airtel, Wave, Telma) or international gateway.
     *
     * Required params:
     *   - body_template  (string)  Max 160 chars after interpolation
     *
     * Required context keys:
     *   - to         (string|array)  Phone number(s) or role name(s)
     *   - tenant_id  (int)
     *
     * Optional params:
     *   - gateway    (string)  orange|mtn|airtel|wave|telma|generic — defaults to 'generic'
     *   - country    (string)  ISO alpha-2 (SN, CI, CM, MG, KE …) — used to pick gateway
     */
    public function sendSms(array $params, array $context): array
    {
        $to          = $context['to']        ?? $params['to']        ?? null;
        $body        = $this->interpolate($params['body_template'] ?? $params['body'] ?? '', $context);
        $tenantId    = $context['tenant_id'] ?? 1;
        $gateway     = $params['gateway']    ?? $this->inferGateway($params['country'] ?? null);

        if (!$to || empty($body)) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : to et body_template sont requis.',
            ];
        }

        $phones = $this->resolvePhoneNumbers((array) $to, $tenantId);

        if (empty($phones)) {
            return [
                'status'  => 'skipped',
                'action'  => 'notify.sms',
                'message' => "Aucun numéro résolu pour : " . implode(', ', (array) $to),
            ];
        }

        // Truncate to SMS limit
        $body = mb_substr($body, 0, 160);

        $sent   = [];
        $errors = [];

        foreach ($phones as $phone) {
            try {
                // Log SMS dispatch — actual gateway call delegated to SMS module
                DB::table('sms_outbox')->insert([
                    'tenant_id'  => $tenantId,
                    'phone'      => $phone,
                    'body'       => $body,
                    'gateway'    => $gateway,
                    'status'     => 'queued',
                    'source'     => 'workflow',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sent[] = $phone;
            } catch (\Throwable $e) {
                $errors[] = "Erreur {$phone}: {$e->getMessage()}";
                Log::warning('NotificationActionHandler::sendSms item error', ['phone' => $phone, 'error' => $e->getMessage()]);
            }
        }

        return [
            'status'  => empty($errors) ? 'success' : 'partial',
            'action'  => 'notify.sms',
            'sent'    => $sent,
            'errors'  => $errors,
            'gateway' => $gateway,
            'message' => sprintf('%d SMS mis en file d\'attente via %s.', count($sent), $gateway),
        ];
    }

    /**
     * action: approval.request_multi_level
     *
     * Creates a multi-level approval request and notifies the first-level
     * approvers.
     *
     * Required params:
     *   - levels    (int)    1 or 3
     *   - approvers (array)  Ordered list of role names (e.g. ['manager','daf','dg'])
     *
     * Required context keys:
     *   - entity_type  (string)  e.g. 'purchase_invoice'
     *   - entity_id    (int)
     *   - amount       (float)
     *   - currency     (string) Defaults to 'XOF'
     *   - tenant_id    (int)
     */
    public function requestMultiLevelApproval(array $params, array $context): array
    {
        $levels     = (int) ($params['levels']    ?? 1);
        $approvers  = (array) ($params['approvers'] ?? ['manager']);
        $entityType = $context['entity_type']  ?? 'unknown';
        $entityId   = $context['entity_id']    ?? $context['invoice_id'] ?? null;
        $amount     = (float) ($context['amount'] ?? 0);
        $currency   = $context['currency']     ?? 'XOF';
        $tenantId   = $context['tenant_id']    ?? 1;

        if (!$entityId) {
            return [
                'status'  => 'error',
                'message' => 'Contexte incomplet : entity_id est requis.',
            ];
        }

        try {
            $requestId = DB::table('workflow_approval_requests')->insertGetId([
                'tenant_id'       => $tenantId,
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'amount'          => $amount,
                'currency'        => $currency,
                'levels'          => $levels,
                'approvers'       => json_encode($approvers),
                'current_level'   => 1,
                'status'          => 'pending',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Notify the first-level approver
            $firstApprover = $approvers[0] ?? 'manager';
            $this->sendInAppNotification(
                [
                    'title_template' => 'Approbation requise — {{entity_type}} #{{entity_id}}',
                    'body_template'  => 'Montant : {{amount}} {{currency}}. Niveau 1/{{levels}}.',
                    'type'           => 'warning',
                    'module'         => 'Workflow',
                ],
                array_merge($context, ['to' => $firstApprover, 'levels' => $levels]),
            );

            return [
                'status'      => 'success',
                'action'      => 'approval.request_multi_level',
                'request_id'  => $requestId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'levels'      => $levels,
                'approvers'   => $approvers,
                'message'     => "Demande d'approbation {$levels} niveau(x) créée (ID #{$requestId}) pour {$entityType} #{$entityId}.",
            ];
        } catch (\Throwable $e) {
            Log::error('NotificationActionHandler::requestMultiLevelApproval error', [
                'params'    => $params,
                'context'   => $context,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => "Erreur lors de la création de la demande d'approbation : {$e->getMessage()}",
            ];
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Replace {{key}} placeholders in a template string with context values.
     *
     * @param  array<string,mixed> $context
     */
    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($context): string {
            return (string) ($context[$m[1]] ?? $m[0]);
        }, $template) ?? $template;
    }

    /**
     * Resolve a list of role names or email addresses to email addresses.
     *
     * @param  list<string> $targets
     * @return list<string>
     */
    private function resolveRecipients(array $targets, int $tenantId): array
    {
        // Chantier 10: this and the two sibling resolve*() helpers below used
        // to filter on `users.tenant_id` with an `orWhereNull('users.tenant_id')`
        // fallback — since that column is the phantom tenant_id (real,
        // migrated, never populated by any real registration/onboarding
        // path, same bug pattern fixed repeatedly this session), the
        // orWhereNull clause was unconditionally true for every user,
        // meaning a role-addressed notification (e.g. "to: payroll-officer")
        // was actually broadcast to that role across ALL tenants, not just
        // the triggering one — a real cross-tenant notification leak. Fixed
        // to filter on the real tenant boundary column, `users.company_id`,
        // with no fallback (matching the fix already applied to Setup/
        // Reporting/Strategy/AI/Sales/etc.).
        $emails = [];

        foreach ($targets as $target) {
            if (str_contains($target, '@')) {
                $emails[] = $target;
            } else {
                // Resolve role → emails via users table
                $roleEmails = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', $target)
                    ->where('users.company_id', $tenantId)
                    ->pluck('users.email')
                    ->toArray();

                $emails = array_merge($emails, $roleEmails);
            }
        }

        return array_unique(array_filter($emails));
    }

    /**
     * Resolve a list of role names or user IDs to integer user IDs.
     *
     * @param  list<string|int> $targets
     * @return list<int>
     */
    private function resolveUserIds(array $targets, int $tenantId): array
    {
        $ids = [];

        foreach ($targets as $target) {
            if (is_numeric($target)) {
                $ids[] = (int) $target;
            } else {
                $roleUserIds = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', $target)
                    ->where('users.company_id', $tenantId)
                    ->pluck('users.id')
                    ->toArray();

                $ids = array_merge($ids, $roleUserIds);
            }
        }

        return array_unique($ids);
    }

    /**
     * Resolve phone numbers (role-based or literal).
     *
     * @param  list<string> $targets
     * @return list<string>
     */
    private function resolvePhoneNumbers(array $targets, int $tenantId): array
    {
        $phones = [];

        foreach ($targets as $target) {
            // Literal phone number (starts with + or digit)
            if (preg_match('/^\+?\d/', $target)) {
                $phones[] = $target;
            } else {
                // Resolve role → phone numbers via users table
                $rolePhones = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', $target)
                    ->where('users.company_id', $tenantId)
                    ->whereNotNull('users.phone')
                    ->pluck('users.phone')
                    ->toArray();

                $phones = array_merge($phones, $rolePhones);
            }
        }

        return array_unique(array_filter($phones));
    }

    /**
     * Infer the best SMS gateway for a given country code.
     *
     * Africa First — maps ISO alpha-2 country codes to their dominant
     * mobile money / telecoms operator.
     */
    private function inferGateway(?string $country): string
    {
        return match (strtoupper((string) $country)) {
            'SN', 'ML', 'BF', 'GN', 'CI' => 'orange',
            'CM', 'GH', 'UG', 'RW', 'ZM' => 'mtn',
            'KE', 'TZ', 'MZ', 'RW'       => 'airtel',
            'MG'                           => 'telma',
            default                        => 'generic',
        };
    }
}
