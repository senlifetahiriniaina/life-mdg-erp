<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ThreatIndicator extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    // Without this, Eloquent's default convention ("threat_indicators")
    // doesn't match the real table created by
    // 2026_06_07_000002_create_security_threat_indicators_table.php —
    // every query against this model failed with "no such table" before
    // this fix, which is why ThreatDetectionService::isKnownThreatIp()
    // had never actually run successfully.
    protected $table = 'security_threat_indicators';

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
