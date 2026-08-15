<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Session Security Event Model
 *
 * Tracks security-related events for sessions:
 * - Session timeouts
 * - Idle timeouts with grace period
 * - Fingerprint mismatches (hijacking attempts)
 * - Concurrent session limit violations
 * - Session regeneration
 * - Session creation and invalidation
 */
class SessionSecurityEvent extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'session_security_events';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'session_id',
        'user_id',
        'event_type',
        'ip_address',
        'old_fingerprint',
        'new_fingerprint',
        'reason',
        'severity',
        'action_taken',
        'created_at',
        'tenant_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'severity' => 'info',
        'action_taken' => 'none',
    ];

    /**
     * Get the associated session
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionEnhanced::class, 'session_id', 'id');
    }

    /**
     * Scope: Critical events only
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope: Warnings and critical events
     */
    public function scopeAlerts($query)
    {
        return $query->whereIn('severity', ['warning', 'critical']);
    }

    /**
     * Scope: For a specific user
     */
    public function scopeForUser($query, int|string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Hijacking attempts
     */
    public function scopeHijackAttempts($query)
    {
        return $query->where('event_type', 'hijack_attempt');
    }

    /**
     * Scope: Recent events (last N minutes)
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
};
