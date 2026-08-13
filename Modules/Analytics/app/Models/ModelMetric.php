<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelMetric extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'model_metrics';

    protected $fillable = [
        'ml_model_version_id',
        'metric_name',
        'metric_value',
        'dataset_type',
        'breakdown',
    ];

    protected $casts = [
        'metric_value' => 'decimal:6',
        'breakdown' => 'array',
    ];

    public function mlModelVersion(): BelongsTo
    {
        return $this->belongsTo(MLModelVersion::class);
    }
}
