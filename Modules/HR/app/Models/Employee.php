<?php

namespace Modules\HR\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\HR\Database\Factories\EmployeeFactory;
use Modules\HR\Services\BankDetailsMaskingService;
use Modules\Helpdesk\Traits\HelpdeskLinkable;
use Modules\Timesheets\Models\TimesheetEntry;

class Employee extends Model
{
    use HasFactory, HelpdeskLinkable, SoftDeletes, RecordsActivity;

    /**
     * Chantier 32.17 (HR deep 14-layer audit): Employee had no real audit
     * trail at all (Layer 11 — zero HR model used RecordsActivity/
     * AuditableActions, the app's real, DB-backed audit trail, despite this
     * being the module with the most PII/compliance sensitivity in the
     * whole app). RecordsActivity's own default (no $auditableFields set)
     * serializes the model's *entire* toArray() into core_audit_logs —
     * which for Employee would decrypt and permanently persist
     * bank_details/national_id/passport_number in plaintext into a table
     * readable by anyone holding auditlog.logs.view* (includes plain
     * 'admin' via wildcard, per this app's own AuditLog documentation) —
     * exactly the class of PII leak already fixed twice elsewhere in this
     * same module (SelfServiceEmployeeResource, and this chantier's own
     * EmployeeWebController::show() fix). Explicitly allowlisted to
     * non-PII organizational fields only.
     */
    protected static array $auditableFields = [
        'employee_number', 'department_id', 'job_position_id', 'manager_id',
        'hire_date', 'probation_end_date', 'termination_date',
        'employment_type', 'status', 'job_title', 'company_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $employee) {
            if (empty($employee->employee_number)) {
                $employee->employee_number = 'EMP'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($employee->hire_date)) {
                $employee->hire_date = now()->toDateString();
            }
        });
    }

    protected $table = 'hr_employees';

    protected $fillable = [
        'company_id',
        'employee_number',
        'user_id',
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'nationality',
        'address',
        'department_id',
        'job_position_id',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'employment_type',
        'manager_id',
        'status',
        'avatar',
        'national_id',
        'national_id_encrypted',
        'passport_number',
        'passport_number_encrypted',
        'bank_details',
        'bank_details_encrypted',
        'emergency_contacts',
        'emergency_contacts_encrypted',
        'phone_encrypted',
        'email_encrypted',
        'termination_reason',
        'sick_leave_balance',
        'annual_leave_balance',
        'job_title',
        // Chantier 32.17 (HR deep 14-layer audit): closes the cross-tenant
        // data leak already flagged twice in this session and never fixed
        // (Chantier 19 Lot 2: "HR has zero company-based tenant isolation
        // anywhere ... confirmed empirically that a Company A hr-manager
        // sees every company's employees"). Nullable/indexed (see the
        // migration), matching the already-proven-safe pattern from
        // Projects (Chantier 10) and CRM (Chantier 19 Lot 1) — a no-op for
        // pre-chantier data or a not-yet-provisioned user, real scoping
        // once both sides carry a real value.
        'company_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        // Encrypted PII fields
        'national_id_encrypted' => 'encrypted',
        'passport_number_encrypted' => 'encrypted',
        'phone_encrypted' => 'encrypted',
        'email_encrypted' => 'encrypted',
        'bank_details' => 'array',
        'bank_details_encrypted' => 'encrypted:array',
        'emergency_contacts' => 'array',
        'emergency_contacts_encrypted' => 'encrypted:array',
    ];

    /**
     * Get the employee's phone number (prefers encrypted version).
     */
    public function getPhoneAttribute()
    {
        return $this->attributes['phone_encrypted'] ?? $this->attributes['phone'] ?? null;
    }

    /**
     * Get the employee's email (prefers encrypted version).
     */
    public function getEmailAttribute()
    {
        return $this->attributes['email_encrypted'] ?? $this->attributes['email'] ?? null;
    }

    /**
     * Get masked bank details for API responses (PCI DSS compliance).
     * Shows only last 4 digits of account/routing numbers.
     */
    public function getMaskedBankDetailsAttribute()
    {
        $bankDetails = $this->attributes['bank_details_encrypted'] ?? $this->attributes['bank_details'] ?? null;

        if (! $bankDetails) {
            return null;
        }

        return BankDetailsMaskingService::maskBankDetails($bankDetails);
    }

    /**
     * Get the employee's national ID (prefers encrypted version).
     */
    public function getNationalIdAttribute()
    {
        return $this->attributes['national_id_encrypted'] ?? $this->attributes['national_id'] ?? null;
    }

    /**
     * Get the employee's passport number (prefers encrypted version).
     */
    public function getPassportNumberAttribute()
    {
        return $this->attributes['passport_number_encrypted'] ?? $this->attributes['passport_number'] ?? null;
    }

    /**
     * Get the employee's associated user account.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the employee's department.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the employee's job position.
     */
    public function jobPosition()
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    /**
     * Get the employee's manager.
     */
    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    protected static function newFactory()
    {
        return EmployeeFactory::new();
    }

    public function position()
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    public function compensations()
    {
        return $this->hasMany(EmployeeCompensation::class, 'employee_id');
    }

    /**
     * Chantier 10: added while wiring Modules\Workflow's HrPayrollActionHandler
     * ::addOvertime() onto real TimesheetEntry rows — TimesheetEntry::employee()
     * already had the inverse belongsTo, but Employee had no hasMany back.
     */
    public function timesheetEntries()
    {
        return $this->hasMany(TimesheetEntry::class, 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getFullName()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function setFullNameAttribute(string $value): void
    {
        $parts = explode(' ', $value, 2);
        $this->attributes['first_name'] = $parts[0];
        $this->attributes['last_name'] = $parts[1] ?? '';
    }

    public function getAge()
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return Carbon::parse($this->date_of_birth)->age;
    }

    public function getYearsOfService()
    {
        return (int) Carbon::parse($this->hire_date)->diffInYears();
    }

    /**
     * Full-name accessor (overrides the stored full_name column).
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    /**
     * Scope employees to a department (accepts a Department model or an id).
     */
    public function scopeByDepartment($query, $department)
    {
        $departmentId = is_object($department) ? $department->id : $department;

        return $query->where('department_id', $departmentId);
    }

    public function archive(): bool
    {
        $this->status = 'archived';

        return $this->save();
    }

    /**
     * Deduct days from the annual leave balance.
     *
     * @throws \Exception when the deduction exceeds the available balance
     */
    public function deductLeave($days): bool
    {
        if ($days > $this->annual_leave_balance) {
            throw new \Exception('Insufficient leave balance.');
        }

        $this->annual_leave_balance -= $days;

        return $this->save();
    }

}
