<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records anomalies detected by AI across ERP modules.
 *
 * @property int              $id
 * @property string           $module        e.g. 'Accounting', 'HR'
 * @property string           $entity_type   e.g. 'Invoice', 'Employee'
 * @property int|null         $entity_id
 * @property string           $anomaly_type  e.g. 'duplicate', 'outlier', 'threshold_breach'
 * @property string           $severity      low|medium|high|critical
 * @property string           $description   Human-readable explanation
 * @property \Carbon\Carbon   $detected_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property array|null       $metadata      JSON: model scores, thresholds, etc.
 */
class AiAnomaly extends Model
{
    public $timestamps = false;

    protected $table = 'ai_anomalies';

    protected $fillable = [
        'module',
        'entity_type',
        'entity_id',
        'anomaly_type',
        'severity',
        'description',
        'detected_at',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'entity_id'   => 'integer',
        'metadata'    => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Scope: only unresolved anomalies.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope: filter by severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Mark the anomaly as resolved.
     */
    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    /**
     * Whether the anomaly has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
