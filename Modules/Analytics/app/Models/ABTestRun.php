<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ABTestRun extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'ab_test_runs';

    protected $fillable = [
        'company_id',
        'ml_model_id',
        'control_version_id',
        'variant_version_id',
        'test_name',
        'hypothesis',
        'status',
        'sample_size',
        'test_split',
        'statistical_significance',
        'confidence_level',
        'started_at',
        'ended_at',
        'results',
        'winner',
        'conclusion',
        'created_by',
    ];

    protected $casts = [
        'test_split' => 'decimal:2',
        'statistical_significance' => 'decimal:4',
        'confidence_level' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'results' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mlModel(): BelongsTo
    {
        // Explicit FK: Laravel's snake_case convention would otherwise mangle
        // `MLModel` into `m_l_model_id` (each capital treated as a word boundary).
        return $this->belongsTo(MLModel::class, 'ml_model_id');
    }

    public function controlVersion(): BelongsTo
    {
        return $this->belongsTo(MLModelVersion::class, 'control_version_id');
    }

    public function variantVersion(): BelongsTo
    {
        return $this->belongsTo(MLModelVersion::class, 'variant_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
