<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecommendationModel extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'recommendation_models';

    protected $fillable = [
        'company_id',
        'model_name',
        'recommendation_type',
        'algorithm',
        'status',
        'description',
        'configuration',
        'coverage_percentage',
        'recommendation_count',
        'click_through_count',
        'ctr',
        'last_trained_at',
        'created_by',
    ];

    protected $casts = [
        'coverage_percentage' => 'decimal:2',
        'ctr' => 'decimal:4',
        'last_trained_at' => 'datetime',
        'configuration' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
