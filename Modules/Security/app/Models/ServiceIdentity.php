<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceIdentity extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'company_id',
        'service_name',
        'service_type',
        'public_key',
        'private_key_hash',
        'allowed_permissions',
        'resource_restrictions',
        'last_rotated_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'allowed_permissions' => 'array',
        'resource_restrictions' => 'array',
        'last_rotated_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
