<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class MLModel extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'ml_models';

    protected $fillable = [
        'company_id',
        'model_key',
        'model_name',
        'model_category',
        'framework',
        'status',
        'description',
        'hyperparameters',
        'production_version',
        'total_versions',
        'production_accuracy',
        'deployed_at',
        'last_retrained_at',
        'inference_count',
        'avg_inference_time_ms',
        'created_by',
        'deployed_by',
    ];

    protected $casts = [
        'hyperparameters' => 'array',
        'production_accuracy' => 'float',
        'avg_inference_time_ms' => 'decimal:2',
        'deployed_at' => 'datetime',
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

    public function deployedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function versions(): HasMany
    {
        // Explicit FK: Laravel's snake_case convention would otherwise mangle
        // `MLModel` into `m_l_model_id` (each capital treated as a word boundary).
        return $this->hasMany(MLModelVersion::class, 'ml_model_id');
    }

    public function abTests(): HasMany
    {
        return $this->hasMany(ABTestRun::class, 'ml_model_id');
    }
}
