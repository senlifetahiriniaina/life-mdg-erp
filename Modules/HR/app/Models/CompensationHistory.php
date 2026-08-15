<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CompensationHistory extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_compensation_history';

    protected $fillable = [
        'employee_id',
        'change_type',
        'previous_amount',
        'new_amount',
        'amount_difference',
        'currency',
        'effective_date',
        'approved_by',
        'reason',
        'status',
        'metadata',
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'amount_difference' => 'decimal:2',
        'effective_date' => 'date',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function getPercentageChange(): float
    {
        if ($this->previous_amount === null || $this->previous_amount <= 0) {
            return 0.0;
        }
        return (($this->new_amount - $this->previous_amount) / $this->previous_amount) * 100;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);
    }
}
