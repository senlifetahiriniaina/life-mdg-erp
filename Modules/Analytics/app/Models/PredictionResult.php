<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PredictionResult extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'prediction_results';

    protected $fillable = [
        'prediction_model_id',
        'company_id',
        'predictable_type',
        'predictable_id',
        'prediction_score',
        'prediction_class',
        'feature_contributions',
        'metadata',
        'predicted_at',
        'actual_outcome_at',
        'actual_outcome',
    ];

    protected $casts = [
        'prediction_score' => 'decimal:4',
        'feature_contributions' => 'array',
        'metadata' => 'array',
        'predicted_at' => 'datetime',
        'actual_outcome_at' => 'datetime',
    ];

    public function predictionModel(): BelongsTo
    {
        return $this->belongsTo(PredictionModel::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function predictable(): MorphTo
    {
        return $this->morphTo();
    }
}
