<?php

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AuthenticationEvent extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    public $timestamps = false;

    protected $table = 'security_authentication_events';

    protected $fillable = [
        'user_id',
        'user_email',
        'event_type',
        'authentication_method',
        'ip_address',
        'user_agent',
        'device_info',
        'status',
        'failure_reason',
        'trust_score',
        'risk_factors',
        'authenticated_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'risk_factors' => 'array',
        'authenticated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
