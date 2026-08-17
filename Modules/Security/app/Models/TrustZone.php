<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Security\Database\Factories\TrustZoneFactory;

class TrustZone extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static function newFactory(): TrustZoneFactory
    {
        return TrustZoneFactory::new();
    }

    protected $table = 'security_trust_zones';

    protected $fillable = [
        'company_id',
        'zone_name',
        'zone_type',
        'description',
        'cidr_blocks',
        'device_policies',
        'authentication_policies',
        'trust_score_minimum',
        'assigned_resources',
    ];

    protected $casts = [
        'cidr_blocks' => 'array',
        'device_policies' => 'array',
        'authentication_policies' => 'array',
        'trust_score_minimum' => 'integer',
        'assigned_resources' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
