<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;

class ThreatIndicator extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $fillable = [
        'indicator_type',
        'indicator_value',
        'threat_level',
        'description',
        'source',
        'is_whitelisted',
        'detected_at',
        'expires_at',
    ];

    protected $casts = [
        'is_whitelisted' => 'boolean',
        'detected_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
