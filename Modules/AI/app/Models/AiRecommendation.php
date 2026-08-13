<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores AI-generated strategic and operational recommendations.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $module               e.g. 'Strategy', 'CRM'
 * @property string      $recommendation_type  e.g. 'kpi_improvement', 'lead_follow_up'
 * @property string      $title
 * @property string      $description
 * @property string      $priority             low|medium|high|critical
 * @property string      $status               pending|accepted|dismissed|implemented
 * @property array|null  $metadata             JSON: extra structured data
 * @property \Carbon\Carbon $created_at
 */
class AiRecommendation extends Model
{
    public $timestamps = false;

    protected $table = 'ai_recommendations';

    protected $fillable = [
        'user_id',
        'module',
        'recommendation_type',
        'title',
        'description',
        'priority',
        'status',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The user this recommendation was generated for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Scope: only pending (unactioned) recommendations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: filter by priority level.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Accept this recommendation.
     */
    public function accept(): void
    {
        $this->update(['status' => 'accepted']);
    }

    /**
     * Dismiss this recommendation.
     */
    public function dismiss(): void
    {
        $this->update(['status' => 'dismissed']);
    }

    /**
     * Mark this recommendation as implemented.
     */
    public function markImplemented(): void
    {
        $this->update(['status' => 'implemented']);
    }
}
