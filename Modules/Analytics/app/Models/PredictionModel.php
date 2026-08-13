<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PredictionModel extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'prediction_models';

    protected $fillable = [
        'company_id',
        'model_name',
        'model_type',
        'status',
        'description',
        'configuration',
        'training_accuracy',
        'validation_accuracy',
        'trained_at',
        'last_used_at',
        'prediction_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'configuration' => 'array',
        'training_accuracy' => 'decimal:4',
        'validation_accuracy' => 'decimal:4',
        'trained_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(PredictionInput::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(PredictionResult::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ModelAccuracyMetric::class);
    }
}
