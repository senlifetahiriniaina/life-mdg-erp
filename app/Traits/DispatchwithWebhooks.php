<?php

declare(strict_types=1);

namespace App\Traits;

use App\Jobs\DispatchWebhookJob;

trait DispatchesWithWebhooks
{
    public static function bootDispatchesWithWebhooks(): void
    {
        static::created(function ($model) {
            $event = self::getWebhookEventName($model, 'created');
            if ($event) {
                DispatchWebhookJob::dispatch($event, $model->toArray());
            }
        });

        static::updated(function ($model) {
            if ($model->isDirty()) {
                $event = self::getWebhookEventName($model, 'updated');
                if ($event) {
                    DispatchWebhookJob::dispatch($event, [
                        'before' => $model->getOriginal(),
                        'after' => $model->getAttributes(),
                        'changed_fields' => $model->getDirty(),
                    ]);
                }
            }
        });

        static::deleted(function ($model) {
            $event = self::getWebhookEventName($model, 'deleted');
            if ($event) {
                DispatchWebhookJob::dispatch($event, $model->toArray());
            }
        });
    }

    private static function getWebhookEventName($model, string $action): ?string
    {
        $modelClass = class_basename($model);
        $moduleMap = [
            'Contact' => 'crm.contact',
            'Lead' => 'crm.lead',
            'Opportunity' => 'crm.opportunity',
            'Account' => 'crm.account',
            'Invoice' => 'accounting.invoice',
            'Journal' => 'accounting.journal',
            'JournalEntry' => 'accounting.journal_entry',
            'Employee' => 'hr.employee',
            'LeaveRequest' => 'hr.leave_request',
            'PayrollRecord' => 'hr.payroll',
            'Product' => 'inventory.product',
            'Ticket' => 'helpdesk.ticket',
            'BomItem' => 'manufacturing.bom',
            'WorkOrder' => 'manufacturing.work_order',
            'Order' => 'ecommerce.order',
            'Project' => 'projects.project',
            'Task' => 'projects.task',
            'Conversation' => 'whatsapp.conversation',
            'Document' => 'documents.document',
        ];

        $prefix = $moduleMap[$modelClass] ?? null;
        return $prefix ? "{$prefix}.{$action}" : null;
    }
}
