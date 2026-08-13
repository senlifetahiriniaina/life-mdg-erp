<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * DDoSIncident Model
 *
 * Tracks detected DDoS attacks and suspicious IP addresses.
 * Implements automated blocking and incident logging for compliance.
 *
 * @property int $id
 * @property string $ip_address
 * @property string|null $endpoint
 * @property string $risk_level
 * @property string $reason
 * @property array|null $attack_signatures
 * @property int $request_count
 * @property float|null $requests_per_second
 * @property Carbon $detected_at
 * @property Carbon|null $blocked_until
 * @property Carbon|null $auto_unblock_at
 * @property bool $auto_blocked
 * @property string|null $attack_signature
 * @property array|null $metrics
 * @property string|null $tenant_id
 * @property Carbon $created_at
 */
class DDoSIncident extends Model
{
    use HasFactory;

    protected $table = 'ddos_incidents';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const RISK_LEVEL_LOW = 'low';
    const RISK_LEVEL_MEDIUM = 'medium';
    const RISK_LEVEL_HIGH = 'high';
    const RISK_LEVEL_CRITICAL = 'critical';

    protected $fillable = [
        'ip_address',
        'endpoint',
        'risk_level',
        'reason',
        'attack_signatures',
        'request_count',
        'requests_per_second',
        'detected_at',
        'blocked_until',
        'auto_unblock_at',
        'auto_blocked',
        'attack_signature',
        'metrics',
        'tenant_id',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'blocked_until' => 'datetime',
        'auto_unblock_at' => 'datetime',
        'created_at' => 'datetime',
        'attack_signatures' => 'array',
        'metrics' => 'array',
        'auto_blocked' => 'boolean',
        'request_count' => 'integer',
        'requests_per_second' => 'float',
    ];

    /**
     * Scope: Filter incidents by IP address.
     */
    public function scopeForIp(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope: Filter active (currently blocked) incidents.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('blocked_until', '>', now())
                     ->orWhereNull('blocked_until');
    }

    /**
     * Scope: Filter currently blocked incidents.
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('blocked_until', '>', now());
    }

    /**
     * Scope: Filter incidents by risk level.
     */
    public function scopeByRiskLevel(Builder $query, string $riskLevel): Builder
    {
        return $query->where('risk_level', $riskLevel);
    }

    /**
     * Scope: Filter critical risk incidents.
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('risk_level', self::RISK_LEVEL_CRITICAL);
    }

    /**
     * Scope: Filter high risk incidents.
     */
    public function scopeHigh(Builder $query): Builder
    {
        return $query->where('risk_level', self::RISK_LEVEL_HIGH);
    }

    /**
     * Scope: Filter incidents by endpoint.
     */
    public function scopeForEndpoint(Builder $query, string $endpoint): Builder
    {
        return $query->where('endpoint', $endpoint);
    }

    /**
     * Scope: Filter incidents by tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter recent incidents within last N seconds.
     */
    public function scopeRecent(Builder $query, int $seconds = 60): Builder
    {
        return $query->where('detected_at', '>=', now()->subSeconds($seconds));
    }

    /**
     * Scope: Filter incidents within time range.
     */
    public function scopeWithinTimeRange(Builder $query, Carbon $startTime, Carbon $endTime): Builder
    {
        return $query->whereBetween('detected_at', [$startTime, $endTime]);
    }

    /**
     * Get all audit log entries for this incident.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    /**
     * Check if this incident is currently active (blocked).
     */
    public function isActive(): bool
    {
        if ($this->blocked_until === null) {
            return false;
        }

        return $this->blocked_until->isFuture();
    }

    /**
     * Check if this incident should be auto-unblocked.
     */
    public function shouldAutoUnblock(): bool
    {
        if ($this->auto_unblock_at === null) {
            return false;
        }

        return $this->auto_unblock_at->isPast();
    }

    /**
     * Get risk level severity score (for sorting/ranking).
     */
    public function getRiskScore(): int
    {
        return match ($this->risk_level) {
            self::RISK_LEVEL_CRITICAL => 4,
            self::RISK_LEVEL_HIGH => 3,
            self::RISK_LEVEL_MEDIUM => 2,
            self::RISK_LEVEL_LOW => 1,
            default => 0,
        };
    }

    /**
     * Get human-readable description of incident.
     */
    public function getDescription(): string
    {
        $desc = "DDoS Incident: {$this->reason}";

        if ($this->request_count > 0) {
            $desc .= " ({$this->request_count} requests";
            if ($this->requests_per_second !== null) {
                $desc .= ", {$this->requests_per_second} req/s";
            }
            $desc .= ")";
        }

        return $desc;
    }
}
