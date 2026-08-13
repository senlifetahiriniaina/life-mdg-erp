<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Integration
 *
 * One row per tenant × external integration (Orange Money, Wave, Shopify, …).
 *
 * @property int         $id
 * @property int         $tenant_id
 * @property string      $integration_key
 * @property string      $name
 * @property string      $status            connected | disconnected | error
 * @property array|null  $credentials       Encrypted JSON
 * @property array|null  $settings          Plain JSON settings
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property int         $sync_count
 * @property int         $error_count
 */
class Integration extends Model
{
    protected $table = 'integrations';

    protected $fillable = [
        'tenant_id',
        'integration_key',
        'name',
        'status',
        'credentials',
        'settings',
        'last_synced_at',
        'sync_count',
        'error_count',
    ];

    protected $casts = [
        'credentials'    => 'encrypted:array',
        'settings'       => 'array',
        'last_synced_at' => 'datetime',
        'sync_count'     => 'integer',
        'error_count'    => 'integer',
    ];

    protected $hidden = ['credentials'];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class, 'integration_id');
    }
}
