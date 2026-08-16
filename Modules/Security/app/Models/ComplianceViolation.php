<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Database\Factories\ComplianceViolationFactory;

class ComplianceViolation extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static function newFactory(): ComplianceViolationFactory
    {
        return ComplianceViolationFactory::new();
    }

    protected $table = 'security_compliance_violations';

    protected $fillable = [
        'company_id',
        'compliance_control_id',
        'violation_type',
        'violation_description',
        'severity',
        'violation_status',
        'detected_at',
        'remediation_deadline',
        'remediated_at',
        'remediation_notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'detected_at' => 'datetime',
        'remediation_deadline' => 'datetime',
        'remediated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function complianceControl(): BelongsTo
    {
        return $this->belongsTo(ComplianceControl::class);
    }
}
