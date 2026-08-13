<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyRotationLog extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $fillable = [
        'encryption_key_id',
        'rotation_type',
        'rotation_status',
        'old_key_hash',
        'new_key_hash',
        'records_reencrypted',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'records_reencrypted' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function encryptionKey(): BelongsTo
    {
        return $this->belongsTo(EncryptionKey::class);
    }
}
