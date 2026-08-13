<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhbConnection extends Model
{
    protected $table = 'whb_connections';

    protected $fillable = [
        'local_tenant_id',
        'remote_tenant_id',
        'remote_server_url',
        'remote_tenant_name',
        'connection_type',
        'status',
        'invite_code',
        'invite_expires_at',
        'shared_secret',
        'session_token',
        'session_expires_at',
        'public_key',
        'initiated_by',
        'approved_by',
        'approved_at',
        'last_sync_at',
    ];

    protected $hidden = [
        'shared_secret',
        'session_token',
    ];

    protected $casts = [
        'invite_expires_at'  => 'datetime',
        'approved_at'        => 'datetime',
        'session_expires_at' => 'datetime',
        'last_sync_at'       => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function permissions(): HasMany
    {
        return $this->hasMany(WhbPermission::class, 'connection_id');
    }

    public function exchanges(): HasMany
    {
        return $this->hasMany(WhbExchange::class, 'connection_id');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('local_tenant_id', $tenantId);
    }

    // ---------------------------------------------------------------------------
    // Business helpers
    // ---------------------------------------------------------------------------

    /**
     * Returns true when the invite code has passed its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->invite_expires_at !== null
            && $this->invite_expires_at->isPast();
    }

    /**
     * Returns true when the current session token is still valid.
     */
    public function isSessionValid(): bool
    {
        return $this->session_expires_at !== null
            && $this->session_expires_at->isFuture();
    }
}
