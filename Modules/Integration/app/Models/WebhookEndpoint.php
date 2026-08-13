<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEndpoint extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'integration_webhook_endpoints';

    protected $fillable = [
        'connector_id',
        'url',
        'method',
        'headers',
        'secret_key',
        'retry_attempts',
        'timeout_seconds',
        'is_active',
    ];

    protected $casts = [
        'headers'       => 'array',
        'is_active'     => 'boolean',
        'retry_attempts' => 'integer',
        'timeout_seconds' => 'integer',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function connector(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnector::class, 'connector_id');
    }
}
