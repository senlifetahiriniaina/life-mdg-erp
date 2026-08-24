<?php

declare(strict_types=1);

namespace Modules\Timesheets\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Modules\HR\Models\Employee;

/**
 * TimesheetPeriod — weekly/bi-weekly period for grouped timesheet submission.
 *
 * Africa First: handles African overtime rules (e.g., OHADA/Senegalese labor code:
 * overtime > 40h/week billed at 125% rate).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property string $period_start  — Y-m-d (Monday)
 * @property string $period_end    — Y-m-d (Sunday)
 * @property float $total_hours
 * @property float $billable_hours
 * @property float $overtime_hours
 * @property string $status  — open | submitted | approved | rejected
 * @property int|null $submitted_by
 * @property string|null $submitted_at
 * @property int|null $approved_by
 * @property string|null $approved_at
 * @property string|null $rejected_reason
 *
 * Computed:
 * @property-read float $utilization_rate  — billable_hours / total_hours × 100
 */
class TimesheetPeriod extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'ts_timesheet_periods';

    protected $fillable = [
        'tenant_id', 'employee_id', 'period_start', 'period_end',
        'total_hours', 'billable_hours', 'overtime_hours', 'status',
        'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'period_start'   => 'date',
        'period_end'     => 'date',
        'total_hours'    => 'decimal:2',
        'billable_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'submitted_at'   => 'datetime',
        'approved_at'    => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Accessors (Phase 49)
    // -------------------------------------------------------------------------

    /**
     * Utilization rate: billable_hours / total_hours × 100.
     */
    public function getUtilizationRateAttribute(): float
    {
        $total = (float) $this->total_hours;
        if ($total === 0.0) {
            return 0.0;
        }
        return round((float) $this->billable_hours / $total * 100, 2);
    }

    /**
     * Africa First: overtime surcharge per OHADA/Senegalese labor code.
     * Hours > 40/week billed at 125%.
     */
    public function getOvertimeSurchargeAttribute(): float
    {
        $overtime = (float) ($this->overtime_hours ?? 0.0);
        return $overtime * 0.25; // 25% surcharge on overtime hours
    }

    // -------------------------------------------------------------------------
    // Business Logic
    // -------------------------------------------------------------------------

    /**
     * Returns true when the period can be submitted (status is 'draft').
     *
     * Chantier 8.4: was 'open' — the only 3 references to that value were
     * this check, its own docblock, and the status this class's own
     * consumer set on create; the real Sheets/*.vue pages (Index's status
     * filter, Show's `v-if="sheet.status === 'draft'"` edit/submit
     * buttons) all use the same 'draft' vocabulary already established by
     * the sibling TimesheetEntry model — aligned rather than left
     * permanently mismatched.
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Returns true when the period can be approved (status is 'submitted').
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'submitted';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Chantier 8.4: this relation referenced Modules\Timesheets\Models\Timesheet
     * (deleted — a broken duplicate of TimesheetEntry with a $fillable that
     * never matched its own timesheets_sheets stub table) via a work_date
     * column that model didn't even declare. Repointed to the real
     * TimesheetEntry model / entry_date column.
     */
    /**
     * Chantier 32.19 (Timesheets deep 14-layer audit): was whereBetween()
     * on the raw column — see TimesheetAdvancedController::approvePeriod()'s
     * docblock for the full write-up of why a bare Y-m-d upper bound
     * silently excludes any entry dated exactly on period_end.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class, 'employee_id', 'employee_id')
            ->whereDate('entry_date', '>=', $this->period_start)
            ->whereDate('entry_date', '<=', $this->period_end);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
