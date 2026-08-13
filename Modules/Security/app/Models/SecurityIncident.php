<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityIncident extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'company_id',
        'incident_type',
        'severity',
        'description',
        'threat_indicators',
        'incident_status',
        'detected_at',
        'investigation_started_at',
        'resolved_at',
        'resolution_notes',
        'affected_resources',
    ];

    protected $casts = [
        'threat_indicators' => 'array',
        'affected_resources' => 'array',
        'detected_at' => 'datetime',
        'investigation_started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(IncidentResponse::class);
    }
}
