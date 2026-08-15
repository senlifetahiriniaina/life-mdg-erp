<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class Recommendation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'recommendations';

    protected $fillable = [
        'recommendation_model_id',
        'company_id',
        'recipient_type',
        'recipient_id',
        'recommended_type',
        'recommended_id',
        'relevance_score',
        'rank',
        'reason',
        'metadata',
        'status',
        'viewed_at',
        'clicked_at',
        'acted_at',
        'expires_at',
    ];

    protected $casts = [
        'relevance_score' => 'decimal:4',
        'metadata' => 'array',
        'viewed_at' => 'datetime',
        'clicked_at' => 'datetime',
        'acted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function recommendationModel(): BelongsTo
    {
        return $this->belongsTo(RecommendationModel::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function recommended(): MorphTo
    {
        return $this->morphTo();
    }
}
