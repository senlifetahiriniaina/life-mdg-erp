<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class EncryptedField extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $fillable = [
        'company_id',
        'table_name',
        'column_name',
        'encryption_algorithm',
        'encryption_key_id',
        'is_searchable',
        'is_encrypted',
        'metadata',
    ];

    protected $casts = [
        'is_searchable' => 'boolean',
        'is_encrypted' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function encryptionKey(): BelongsTo
    {
        return $this->belongsTo(EncryptionKey::class);
    }
}
