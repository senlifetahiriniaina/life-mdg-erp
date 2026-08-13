<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deduction extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_deductions';

    protected $fillable = [
        'employee_id',
        'name',
        'type',
        'category',
        'amount',
        'frequency',
        'limit',
        'ytd_amount',
        'is_active',
        'effective_from',
        'effective_to',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'limit' => 'decimal:2',
        'ytd_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function isEffective(): bool
    {
        return $this->is_active &&
               now()->greaterThanOrEqualTo($this->effective_from) &&
               (is_null($this->effective_to) || now()->lessThanOrEqualTo($this->effective_to));
    }

    public function canDeductAmount(float $amount): bool
    {
        if (!$this->isEffective()) {
            return false;
        }

        if ($this->limit === null) {
            return true;
        }

        return ($this->ytd_amount + $amount) <= $this->limit;
    }

    public function addToYTD(float $amount): void
    {
        $this->increment('ytd_amount', $amount);
    }

    public function isPreTax(): bool
    {
        return $this->category === 'pre_tax';
    }
}
