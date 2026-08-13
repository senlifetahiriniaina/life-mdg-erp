<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionInput extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'prediction_inputs';

    protected $fillable = [
        'prediction_model_id',
        'feature_name',
        'feature_type',
        'data_source',
        'field_mapping',
        'transformation',
        'importance_score',
        'is_required',
    ];

    protected $casts = [
        'transformation' => 'array',
        'importance_score' => 'decimal:4',
        'is_required' => 'boolean',
    ];

    public function predictionModel(): BelongsTo
    {
        return $this->belongsTo(PredictionModel::class);
    }
}
