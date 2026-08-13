<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentResponse extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $fillable = [
        'security_incident_id',
        'response_type',
        'response_status',
        'response_config',
        'executed_at',
        'execution_result',
    ];

    protected $casts = [
        'response_config' => 'array',
        'executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function securityIncident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class);
    }
}
