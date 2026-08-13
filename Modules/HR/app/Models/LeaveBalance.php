<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveBalance extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'employee_id',
        'leave_type',
        'accrual_days_per_year',
        'balance',
        'used',
        'pending',
        'year',
        'reset_date',
        'notes',
    ];

    protected $casts = [
        'accrual_days_per_year' => 'decimal:2',
        'balance' => 'decimal:2',
        'used' => 'decimal:2',
        'pending' => 'decimal:2',
        'reset_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get available balance (balance - pending).
     */
    public function getAvailableBalance(): float
    {
        return max(0, (float) ($this->balance - $this->pending));
    }

    /**
     * Check if enough balance for the requested days.
     */
    public function hasBalance(float $days): bool
    {
        return $this->getAvailableBalance() >= $days;
    }

    /**
     * Deduct from balance (after approval).
     */
    public function deductBalance(float $days): void
    {
        $this->update([
            'balance' => max(0, $this->balance - $days),
            'used' => $this->used + $days,
            'pending' => max(0, $this->pending - $days),
        ]);
    }

    /**
     * Add pending balance (when request submitted).
     */
    public function addPending(float $days): void
    {
        $this->update([
            'pending' => $this->pending + $days,
        ]);
    }

    /**
     * Remove pending balance (when request rejected).
     */
    public function removePending(float $days): void
    {
        $this->update([
            'pending' => max(0, $this->pending - $days),
        ]);
    }
}
