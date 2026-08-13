<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLimit extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'ai_usage_limits';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'limit_type',
        'limit_value',
        'period',
        'block_on_exceed',
        'active',
    ];

    protected $casts = [
        'limit_value'     => 'decimal:4',
        'block_on_exceed' => 'boolean',
        'active'          => 'boolean',
    ];
}
