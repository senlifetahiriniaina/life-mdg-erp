<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class WhbPermission extends Model
{
    use HasFactory;

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
