<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Security\Database\Factories\ComplianceControlFactory;

class ComplianceControl extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static function newFactory(): ComplianceControlFactory
    {
        return ComplianceControlFactory::new();
    }

    protected $table = 'security_compliance_controls';

    protected $fillable = [
        'company_id',
        'framework',
        'control_id',
        'control_name',
        'control_description',
        'control_type',
        'implementation_status',
        'implementation_details',
        'last_verified_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'implementation_details' => 'array',
        'last_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ComplianceViolation::class);
    }
}
