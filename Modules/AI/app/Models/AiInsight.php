<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores AI-generated insights for ERP entities.
 *
 * @property int         $id
 * @property string      $module          e.g. 'CRM', 'Accounting'
 * @property string      $entity_type     e.g. 'Contact', 'Invoice'
 * @property int|null    $entity_id
 * @property string      $insight_type    e.g. 'anomaly', 'trend', 'recommendation'
 * @property string      $content         Human-readable insight text
 * @property float       $confidence_score  0.0–1.0
 * @property bool        $is_read
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 */
class AiInsight extends Model
{
    public $timestamps = false;

    protected $table = 'ai_insights';

    protected $fillable = [
        'module',
        'entity_type',
        'entity_id',
        'insight_type',
        'content',
        'confidence_score',
        'is_read',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'entity_id'        => 'integer',
        'confidence_score' => 'decimal:4',
        'is_read'          => 'boolean',
        'expires_at'       => 'datetime',
        'created_at'       => 'datetime',
    ];

    /**
     * Scope: only non-expired insights.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: unread insights.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Mark this insight as read.
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }
}
