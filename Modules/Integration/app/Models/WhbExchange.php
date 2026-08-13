<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhbExchange extends Model
{
    protected $table = 'whb_exchanges';

    protected $fillable = [
        'connection_id',
        'direction',
        'data_type',
        'local_resource_type',
        'local_resource_id',
        'remote_resource_id',
        'payload',
        'status',
        'error_message',
        'initiated_by',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhbConnection::class, 'connection_id');
    }
}
