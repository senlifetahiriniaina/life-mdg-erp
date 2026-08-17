<?php

namespace Modules\Analytics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class MLModelVersion extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'ml_model_versions';

    protected $fillable = [
        'ml_model_id',
        'version_number',
        'change_notes',
        'validation_accuracy',
        'validation_precision',
        'validation_recall',
        'validation_f1',
        'training_samples',
        'validation_samples',
        'trained_at',
        'model_path',
        'training_config',
        'status',
        'activated_at',
        'deactivated_at',
        'created_by',
    ];

    protected $casts = [
        'validation_accuracy' => 'decimal:4',
        'validation_precision' => 'decimal:4',
        'validation_recall' => 'decimal:4',
        'validation_f1' => 'decimal:4',
        'trained_at' => 'datetime',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'training_config' => 'array',
    ];

    public function mlModel(): BelongsTo
    {
        // Explicit FK: Laravel's snake_case convention would otherwise mangle
        // `MLModel` into `m_l_model_id` (each capital treated as a word boundary).
        return $this->belongsTo(MLModel::class, 'ml_model_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function metrics(): HasMany
    {
        // Same MLModelVersion -> m_l_model_version_id default-FK-guess gotcha as mlModel() above.
        return $this->hasMany(ModelMetric::class, 'ml_model_version_id');
    }
}
