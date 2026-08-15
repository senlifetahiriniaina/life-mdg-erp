<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * OnboardingStepEvent
 *
 * Granular event log for each wizard step transition.
 * Provides per-step drop-off analysis and time-on-step breakdown.
 *
 * @property int          $id
 * @property int          $tenant_id
 * @property int          $user_id
 * @property int          $onboarding_session_id
 * @property int          $step
 * @property string       $event
 * @property int|null     $duration_seconds
 * @property array|null   $metadata
 * @property Carbon       $created_at
 */
class OnboardingStepEvent extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'setup_onboarding_step_events';

    // step events have no updated_at
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'onboarding_session_id',
        'step',
        'event',
        'duration_seconds',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return BelongsTo<OnboardingSession, self> */
    public function onboardingSession(): BelongsTo
    {
        return $this->belongsTo(OnboardingSession::class, 'onboarding_session_id');
    }
}
