<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelAccuracyMetric extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'model_accuracy_metrics';

    protected $fillable = [
        'prediction_model_id',
        'metric_date',
        'metric_type',
        'metric_value',
        'sample_size',
        'breakdown_by_segment',
        'data_period',
    ];

    protected $casts = [
        'metric_value' => 'decimal:4',
        'breakdown_by_segment' => 'array',
        'metric_date' => 'datetime',
    ];

    public function predictionModel(): BelongsTo
    {
        return $this->belongsTo(PredictionModel::class);
    }
}
