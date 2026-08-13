<?php

namespace Modules\Security\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrustZone extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'company_id',
        'zone_name',
        'zone_type',
        'description',
        'cidr_blocks',
        'device_policies',
        'authentication_policies',
        'trust_score_minimum',
    ];

    protected $casts = [
        'cidr_blocks' => 'array',
        'device_policies' => 'array',
        'authentication_policies' => 'array',
        'trust_score_minimum' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
