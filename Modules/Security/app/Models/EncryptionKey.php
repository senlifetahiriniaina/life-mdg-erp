<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Security\Database\Factories\EncryptionKeyFactory;

class EncryptionKey extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static function newFactory(): EncryptionKeyFactory
    {
        return EncryptionKeyFactory::new();
    }

    protected $table = 'security_encryption_keys';

    protected $fillable = [
        'company_id',
        'key_name',
        'key_type',
        'key_usage',
        'key_status',
        'key_material_hash',
        'vault_reference',
        'key_length_bits',
        'created_at',
        'rotated_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'key_length_bits' => 'integer',
        'created_at' => 'datetime',
        'rotated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rotationLogs(): HasMany
    {
        return $this->hasMany(KeyRotationLog::class);
    }

    public function encryptedFields(): HasMany
    {
        return $this->hasMany(EncryptedField::class);
    }
}
