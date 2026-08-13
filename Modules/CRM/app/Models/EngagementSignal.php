<?php

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\EngagementSignalFactory;

/**
 * @property int $id
 * @property int $opportunity_id
 * @property string $signal_type
 * @property int $score_impact
 * @property string|null $description
 * @property string|null $source
 * @property Carbon $occurred_at
 * @property int|null $activity_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EngagementSignal extends Model
{
    use HasFactory;

    protected $table = 'crm_engagement_signals';

    protected $fillable = [
        'opportunity_id',
        'signal_type',
        'score_impact',
        'description',
        'source',
        'occurred_at',
        'activity_id',
    ];

    protected $casts = [
        'score_impact' => 'integer',
        'occurred_at' => 'datetime',
    ];

    protected static function newFactory(): EngagementSignalFactory
    {
        return EngagementSignalFactory::new();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function isRecent(): bool
    {
        return $this->occurred_at->isAfter(now()->subDays(14));
    }
}
