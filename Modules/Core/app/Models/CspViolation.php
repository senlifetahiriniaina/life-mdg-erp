<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CSP Violation Model
 *
 * Represents a Content Security Policy violation logged by the browser or detected on the server.
 * Used for monitoring policy violations and analyzing security threats.
 *
 * Features:
 * - Tracks violated directives and blocked resources
 * - Captures source code location (file, line, column)
 * - Records request context (IP, User-Agent, user)
 * - Classifies severity for alerting
 * - Supports multi-tenant isolation
 */
class CspViolation extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    /**
     * The table associated with the model.
     */
    protected $table = 'csp_violations';

    /**
     * Indicates if the model uses timestamps.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'document_uri',
        'violated_directive',
        'effective_directive',
        'original_policy',
        'disposition',
        'blocked_uri',
        'source_file',
        'line_number',
        'column_number',
        'status_code',
        'ip_address',
        'user_agent',
        'user_id',
        'tenant_id',
        'module',
        'violation_data',
        'is_internal_request',
        'severity',
        'resolved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'violation_data' => AsCollection::class,
        'is_internal_request' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user who triggered this violation.
     *
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant associated with this violation.
     *
     * @return BelongsTo<Tenant>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Filter violations by directive.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $directive
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDirective($query, string $directive)
    {
        return $query->where('violated_directive', $directive);
    }

    /**
     * Scope: Filter violations by severity.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $severity
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySeverity($query, $severity)
    {
        if (is_array($severity)) {
            return $query->whereIn('severity', $severity);
        }

        return $query->where('severity', $severity);
    }

    /**
     * Scope: Filter unresolved violations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope: Filter violations for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter violations for a specific IP address.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $ipAddress
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope: Filter violations for a specific module.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $module
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope: Filter violations within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|\DateTime $from
     * @param string|\DateTime $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Mark this violation as resolved.
     *
     * @return bool
     */
    public function resolve(): bool
    {
        return (bool) $this->update(['resolved_at' => now()]);
    }

    /**
     * Check if this violation is a high-severity threat.
     *
     * @return bool
     */
    public function isHighSeverity(): bool
    {
        return in_array($this->severity, ['high', 'critical']);
    }

    /**
     * Get a human-readable description of the violation.
     *
     * @return string
     */
    public function getDescription(): string
    {
        $blocked = $this->blocked_uri ? " - blocked: {$this->blocked_uri}" : '';

        return "{$this->violated_directive} violation on {$this->document_uri}{$blocked}";
    }
}
