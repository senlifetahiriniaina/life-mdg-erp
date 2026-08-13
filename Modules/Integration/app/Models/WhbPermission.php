<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhbPermission extends Model
{
    protected $table = 'whb_permissions';

    protected $fillable = [
        'connection_id',
        'data_type',
        'can_receive',
        'can_send',
        'auto_accept',
    ];

    protected $casts = [
        'can_receive' => 'boolean',
        'can_send'    => 'boolean',
        'auto_accept' => 'boolean',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhbConnection::class, 'connection_id');
    }
}
