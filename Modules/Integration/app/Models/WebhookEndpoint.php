<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class WebhookEndpoint extends Model
{
    use HasFactory;
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

    /**
     * Chantier 32.6: secret_key was never hidden — IntegrationController::
     * show()/logs() eager-load webhookEndpoints and return it as raw JSON,
     * exposing the HMAC signing secret in plaintext to anyone with `view`
     * permission on the connector. Matches WhbConnection's own established
     * precedent in this exact module (shared_secret/session_token hidden
     * there too, even from the owning tenant) — least-exposure by default,
     * not just an access-control question.
     */
    protected $hidden = [
        'secret_key',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function connector(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnector::class, 'connector_id');
    }
}
