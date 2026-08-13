<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source_tenant_id
 * @property string $target_tenant_id
 * @property string $exchange_type
 * @property array<string,mixed> $payload
 * @property string $status
 * @property string|null $message
 * @property string|null $rejection_reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $created_by_user_id
 * @property int|null $accepted_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantExchange extends Model
{
    use HasFactory;
    protected $table = 'core_tenant_exchanges';

    protected $fillable = [
        'source_tenant_id',
        'target_tenant_id',
        'exchange_type',
        'payload',
        'status',
        'message',
        'rejection_reason',
        'expires_at',
        'accepted_at',
        'created_by_user_id',
        'accepted_by_user_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'source_tenant_id' => 'string',
        'target_tenant_id' => 'string',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TenantExchangeHistory::class, 'exchange_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }
}
