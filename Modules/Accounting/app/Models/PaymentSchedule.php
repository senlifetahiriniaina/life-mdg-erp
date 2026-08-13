<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSchedule extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'accounting_payment_schedules';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'due_date',
        'amount',
        'currency',
        'payment_method',
        'status',
        'reminder_sent_at',
        'calendar_event_id',
    ];

    protected $casts = [
        'due_date'         => 'date',
        'reminder_sent_at' => 'datetime',
        'amount'           => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isOverdue(): bool
    {
        return $this->status === 'scheduled' && $this->due_date->isPast();
    }
}
