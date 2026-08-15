<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Session Enhanced Model
 *
 * Extends Laravel's native sessions table with security features:
 * - Device fingerprinting
 * - Session fixation prevention
 * - Concurrent session limits
 * - Activity tracking
 * - Multi-tenant isolation
 */
class SessionEnhanced extends Model
{
    use HasFactory;

    protected $table = 'sessions_enhanced';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent_hash',
        'device_fingerprint',
        'browser_fingerprint',
        'device_type',
        'created_at',
        'last_activity_at',
        'expires_at',
        'fingerprint_checked_at',
        'regeneration_count',
        'concurrent_session_number',
        'suspicious_activity_count',
        'tenant_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'fingerprint_checked_at' => 'datetime',
        'regeneration_count' => 'int',
        'concurrent_session_number' => 'int',
        'suspicious_activity_count' => 'int',
    ];

    /**
     * Get security events for this session
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SessionSecurityEvent::class, 'session_id', 'id');
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if session is idle
     */
    public function isIdle(int $timeoutMinutes = 15): bool
    {
        $idleThreshold = now()->subMinutes($timeoutMinutes);
        return $this->last_activity_at->lessThan($idleThreshold);
    }

    /**
     * Get time remaining until expiration in seconds
     */
    public function timeUntilExpiration(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        $seconds = $this->expires_at->diffInSeconds(now(), absolute: false);
        return max(0, $seconds);
    }

    /**
     * Scope: Active sessions only
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: Expired sessions
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: For a specific user
     */
    public function scopeForUser($query, int|string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: For a specific tenant
     */
    public function scopeForTenant($query, ?string $tenantId)
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }
};
