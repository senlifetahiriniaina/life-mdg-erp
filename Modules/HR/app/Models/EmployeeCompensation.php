<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeCompensation extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_employee_compensation';

    protected $fillable = [
        'employee_id',
        'base_salary',
        'currency',
        'bonus_amount',
        'bonus_frequency',
        'equity_granted',
        'equity_vesting_period_months',
        'equity_vesting_schedule',
        'equity_vested_percentage',
        'benefits_annual_value',
        'total_compensation',
        'effective_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'equity_granted' => 'decimal:4',
        'equity_vested_percentage' => 'decimal:2',
        'benefits_annual_value' => 'decimal:2',
        'total_compensation' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'equity_vesting_schedule' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Calculate total compensation including all components.
     */
    public function calculateTotalCompensation(): float
    {
        $base = $this->base_salary ?? 0;
        $bonus = $this->bonus_amount ?? 0;
        $benefits = $this->benefits_annual_value ?? 0;
        $equity = $this->equity_granted ?? 0;

        return (float) ($base + $bonus + $benefits + $equity);
    }

    /**
     * Calculate vested equity amount.
     */
    public function calculateVestedEquity(): float
    {
        if (!$this->equity_granted || !$this->equity_vested_percentage) {
            return 0;
        }

        return (float) ($this->equity_granted * ($this->equity_vested_percentage / 100));
    }
}
