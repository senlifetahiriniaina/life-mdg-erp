<?php

declare(strict_types=1);

namespace Modules\Calendar\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $provider  google|outlook|apple
 * @property string $access_token  encrypted
 * @property string|null $refresh_token  encrypted
 * @property Carbon|null $token_expires_at
 * @property array|null $calendar_ids
 * @property Carbon|null $last_synced_at
 * @property array|null $sync_errors
 */
class CalendarSyncToken extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'calendar_sync_tokens';

    protected $fillable = [
        'tenant_id', 'user_id', 'provider',
        'access_token', 'refresh_token', 'token_expires_at',
        'calendar_ids', 'last_synced_at', 'sync_errors',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_synced_at'   => 'datetime',
        'calendar_ids'     => 'array',
        'sync_errors'      => 'array',
    ];

    /** Hide encrypted token values from serialization. */
    protected $hidden = ['access_token', 'refresh_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->isPast();
    }

    public function needsRefresh(int $bufferSeconds = 300): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->subSeconds($bufferSeconds)->isPast();
    }
}
