<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ComplianceAudit extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $fillable = [
        'company_id',
        'audit_type',
        'framework',
        'audit_start_date',
        'audit_end_date',
        'controls_evaluated',
        'controls_compliant',
        'controls_non_compliant',
        'compliance_score',
        'findings',
        'audit_status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'audit_start_date' => 'datetime',
        'audit_end_date' => 'datetime',
        'controls_evaluated' => 'integer',
        'controls_compliant' => 'integer',
        'controls_non_compliant' => 'integer',
        'compliance_score' => 'decimal:2',
        'findings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
