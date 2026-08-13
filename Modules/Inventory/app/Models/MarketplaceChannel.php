<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceChannel extends Model
{
    public const TYPES = ['amazon', 'ebay'];

    protected $table = 'marketplace_channels';

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'config',
        'status',
        'last_synced_at',
        'sync_stats',
        'company_id',
    ];

    protected $casts = [
        'config'         => 'encrypted:array',
        'sync_stats'     => 'array',
        'last_synced_at' => 'datetime',
    ];
}
