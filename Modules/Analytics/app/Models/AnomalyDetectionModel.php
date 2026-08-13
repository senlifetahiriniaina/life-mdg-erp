<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnomalyDetectionModel extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'anomaly_detection_models';

    protected $fillable = [
        'company_id',
        'model_name',
        'anomaly_type',
        'algorithm',
        'status',
        'description',
        'configuration',
        'anomaly_threshold',
        'detection_count',
        'true_positive_count',
        'precision',
        'last_retrained_at',
        'created_by',
    ];

    protected $casts = [
        'anomaly_threshold' => 'decimal:4',
        'precision' => 'decimal:4',
        'configuration' => 'array',
        'last_retrained_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(DetectedAnomaly::class);
    }
}
