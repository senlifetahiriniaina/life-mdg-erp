<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetectedAnomaly extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'detected_anomalies';

    protected $fillable = [
        'anomaly_detection_model_id',
        'company_id',
        'anomalous_entity_type',
        'anomalous_entity_id',
        'anomaly_score',
        'severity',
        'description',
        'detected_features',
        'baseline_metrics',
        'status',
        'resolution_notes',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'anomaly_score' => 'decimal:4',
        'detected_features' => 'array',
        'baseline_metrics' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function anomalyDetectionModel(): BelongsTo
    {
        return $this->belongsTo(AnomalyDetectionModel::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function anomalousEntity(): MorphTo
    {
        return $this->morphTo();
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnomalyAlert::class);
    }
}
