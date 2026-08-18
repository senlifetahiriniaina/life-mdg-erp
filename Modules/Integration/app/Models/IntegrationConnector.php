<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationConnector extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'integration_connectors';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'provider_type',
        'config',
        'status',
        'last_sync_at',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'config'       => 'encrypted:array',
        'last_sync_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class, 'connector_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'connector_id');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * tenant_id is a string(36) column (leftover UUID-tenant design, same
     * pattern documented for Security's company_id columns) — accept
     * int|string and always compare as string to avoid a silent type
     * mismatch against $user->company_id (an int).
     */
    public function scopeForTenant($query, int|string $tenantId)
    {
        return $query->where('tenant_id', (string) $tenantId);
    }

    public function scopeByProvider($query, string $providerType)
    {
        return $query->where('provider_type', $providerType);
    }
}
