<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $created_by
 * @property string $title
 * @property string $content
 * @property string $category
 * @property string $language
 * @property array  $tags
 * @property string $use_case
 * @property array  $variables
 * @property string $tone
 * @property int    $avg_resolution_time_minutes
 * @property float  $avg_satisfaction_rating
 * @property int    $usage_count
 * @property int    $positive_feedback_count
 * @property int    $negative_feedback_count
 * @property string $status
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ResponseTemplate extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_response_templates';

    protected $fillable = [
        'created_by',
        'title',
        'content',
        'category',
        'language',
        'tags',
        'use_case',
        'variables',
        'tone',
        'avg_resolution_time_minutes',
        'avg_satisfaction_rating',
        'usage_count',
        'positive_feedback_count',
        'negative_feedback_count',
        'status',
        'notes',
    ];

    protected $casts = [
        'tags' => 'json',
        'variables' => 'json',
        'avg_satisfaction_rating' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(AIResponseVariant::class, 'template_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ResponseSuggestion::class, 'template_id');
    }

    public function performance(): HasMany
    {
        return $this->hasMany(ResponsePerformance::class, 'template_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getSatisfactionRate(): float
    {
        $total = $this->positive_feedback_count + $this->negative_feedback_count;
        if ($total === 0) {
            return 0;
        }

        return $this->positive_feedback_count / $total;
    }

    public function getAvgResolutionHours(): float
    {
        return $this->avg_resolution_time_minutes ? $this->avg_resolution_time_minutes / 60 : 0;
    }
}
