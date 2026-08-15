<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * OnboardingSession
 *
 * Tracks a single user's passage through the 5-step onboarding wizard.
 * Used to measure "Simplicity First" KPI: < 5 minutes to complete.
 *
 * @property int                  $id
 * @property int                  $tenant_id
 * @property int                  $user_id
 * @property Carbon               $started_at
 * @property Carbon|null          $completed_at
 * @property Carbon|null          $abandoned_at
 * @property int                  $current_step
 * @property int|null             $total_duration_seconds
 * @property string               $source_type
 * @property int                  $rows_imported
 * @property bool                 $ai_mapping_used
 * @property float|null           $ai_mapping_accepted_percent
 * @property int                  $errors_count
 * @property Carbon               $created_at
 * @property Carbon               $updated_at
 */
class OnboardingSession extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'setup_onboarding_sessions';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'started_at',
        'completed_at',
        'abandoned_at',
        'current_step',
        'total_duration_seconds',
        'source_type',
        'rows_imported',
        'ai_mapping_used',
        'ai_mapping_accepted_percent',
        'errors_count',
    ];

    protected $casts = [
        'started_at'                 => 'datetime',
        'completed_at'               => 'datetime',
        'abandoned_at'               => 'datetime',
        'ai_mapping_used'            => 'boolean',
        'ai_mapping_accepted_percent' => 'decimal:2',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return HasMany<OnboardingStepEvent> */
    public function stepEvents(): HasMany
    {
        return $this->hasMany(OnboardingStepEvent::class, 'onboarding_session_id');
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /** @param Builder<self> $query */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /** @param Builder<self> $query */
    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->whereNotNull('abandoned_at');
    }

    /** @param Builder<self> $query */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Duration in minutes (null if not yet completed or abandoned).
     */
    public function getDurationMinutes(): ?float
    {
        if ($this->total_duration_seconds === null) {
            return null;
        }

        return round($this->total_duration_seconds / 60, 2);
    }

    /**
     * "Simplicity First" KPI: true when onboarding was completed in ≤ 5 minutes (300 seconds).
     */
    public function isCompletedUnder5Min(): bool
    {
        return $this->total_duration_seconds !== null
            && $this->total_duration_seconds <= 300;
    }

    /**
     * Mark the session as successfully completed.
     * Calculates and persists total_duration_seconds.
     */
    public function complete(int $rowsImported = 0, bool $aiUsed = false, ?float $aiAcceptedPercent = null): void
    {
        $now = Carbon::now();
        $duration = (int) $this->started_at->diffInSeconds($now);

        $this->update([
            'completed_at'               => $now,
            'total_duration_seconds'     => $duration,
            'current_step'               => 5,
            'rows_imported'              => $rowsImported,
            'ai_mapping_used'            => $aiUsed,
            'ai_mapping_accepted_percent' => $aiAcceptedPercent,
        ]);
    }

    /**
     * Mark the session as abandoned at the given step.
     */
    public function abandon(int $atStep): void
    {
        $this->update([
            'abandoned_at'  => Carbon::now(),
            'current_step'  => $atStep,
        ]);
    }
}
