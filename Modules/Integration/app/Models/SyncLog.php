<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'integration_sync_logs';

    protected $fillable = [
        'connector_id',
        'tenant_id',
        'direction',
        'status',
        'payload_size',
        'records_processed',
        'records_failed',
        'error_details',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'error_details' => 'array',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function connector(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnector::class, 'connector_id');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
