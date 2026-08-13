<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                       $id
 * @property int                       $company_id
 * @property int                       $created_by
 * @property string                    $name
 * @property string|null               $description
 * @property string                    $source_type
 * @property string                    $status
 * @property array<string, mixed>      $connection_config
 * @property string                    $authentication_type
 * @property bool                      $is_test_connection
 * @property \Carbon\Carbon|null       $last_test_at
 * @property \Carbon\Carbon|null       $last_successful_sync
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\User     $creator
 * @property-read \App\Models\Company  $company
 */
class ExternalDataSource extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_external_data_sources';

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'source_type',
        'status',
        'connection_config',
        'authentication_type',
        'is_test_connection',
        'last_test_at',
    ];

    protected $casts = [
        'connection_config'  => 'array',
        'is_test_connection' => 'boolean',
        'last_test_at'       => 'datetime',
        'last_successful_sync' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ExternalCredential::class, 'source_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(FieldMapping::class, 'source_id');
    }

    public function syncConfiguration(): HasMany
    {
        return $this->hasMany(SyncConfiguration::class, 'source_id');
    }

    public function syncHistory(): HasMany
    {
        return $this->hasMany(SyncHistory::class, 'source_id')->latest();
    }

    public function transformationRules(): HasMany
    {
        return $this->hasMany(TransformationRule::class, 'source_id')->orderBy('rule_order');
    }

    public function dataRefreshSchedule(): HasMany
    {
        return $this->hasMany(DataRefreshSchedule::class, 'source_id');
    }

    public function connect(): void
    {
        $this->update(['status' => 'connected']);
    }

    public function disconnect(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function markSyncing(): void
    {
        $this->update(['status' => 'syncing']);
    }

    public function markError(): void
    {
        $this->update(['status' => 'error']);
    }

    public function recordSuccessfulSync(): void
    {
        $this->update([
            'status'                 => 'connected',
            'last_successful_sync'   => now(),
        ]);
    }

    public function recordTestConnection(): void
    {
        $this->update([
            'is_test_connection' => true,
            'last_test_at'       => now(),
        ]);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function getSourceTypeLabel(): string
    {
        return match ($this->source_type) {
            'google_analytics' => 'Google Analytics',
            'shopify'          => 'Shopify',
            'salesforce'       => 'Salesforce',
            'hubspot'          => 'HubSpot',
            'stripe'           => 'Stripe',
            'custom_api'       => 'Custom API',
            default            => ucfirst(str_replace('_', ' ', $this->source_type)),
        };
    }

    public function getAuthenticationTypeLabel(): string
    {
        return match ($this->authentication_type) {
            'oauth'       => 'OAuth',
            'api_key'     => 'API Key',
            'basic_auth'  => 'Basic Auth',
            'bearer_token' => 'Bearer Token',
            default       => $this->authentication_type,
        };
    }
}
