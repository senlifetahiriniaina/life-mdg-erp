<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * RateLimitMetrics Model
 *
 * Tracks all rate limit requests for analytics and monitoring.
 * Supports tenant isolation and per-endpoint analysis.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $endpoint
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $status_code
 * @property int|null $response_time_ms
 * @property Carbon $timestamp
 * @property string|null $tenant_id
 * @property Carbon $created_at
 */
class RateLimitMetrics extends Model
{
    use HasFactory;

    protected $table = 'rate_limit_metrics';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'endpoint',
        'ip_address',
        'user_agent',
        'status_code',
        'response_time_ms',
        'timestamp',
        'tenant_id',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'user_id' => 'integer',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /**
     * Scope: Filter metrics by endpoint.
     */
    public function scopeForEndpoint(Builder $query, string $endpoint): Builder
    {
        return $query->where('endpoint', $endpoint);
    }

    /**
     * Scope: Filter metrics by user ID.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter metrics by IP address.
     */
    public function scopeForIp(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope: Filter metrics by tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter recent metrics within last N seconds.
     */
    public function scopeRecent(Builder $query, int $seconds = 60): Builder
    {
        return $query->where('timestamp', '>=', now()->subSeconds($seconds));
    }

    /**
     * Scope: Filter by status code.
     */
    public function scopeWithStatus(Builder $query, int $statusCode): Builder
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope: Filter by time range.
     */
    public function scopeWithinTimeRange(Builder $query, Carbon $startTime, Carbon $endTime): Builder
    {
        return $query->whereBetween('timestamp', [$startTime, $endTime]);
    }

    /**
     * Scope: Filter successful requests (status_code < 400).
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status_code', '<', 400);
    }

    /**
     * Scope: Filter failed requests (status_code >= 400).
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope: Filter metrics within a time window in seconds.
     */
    public function scopeWithinWindow(Builder $query, int $seconds = 60): Builder
    {
        return $query->where('timestamp', '>=', now()->subSeconds($seconds));
    }

    /**
     * Get the average response time for a metric set.
     */
    public function getAverageResponseTime(): ?float
    {
        return $this->avg('response_time_ms');
    }

    /**
     * Get request count for metrics.
     */
    public function getRequestCount(): int
    {
        return $this->count();
    }
}
