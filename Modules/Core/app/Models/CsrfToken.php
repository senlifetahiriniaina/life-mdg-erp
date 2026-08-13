<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $token_hash
 * @property string|null $action
 * @property string|null $scope
 * @property string|null $ip_address
 * @property string|null $user_agent_hash
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_verified_at
 * @property int $rotation_count
 * @property string|null $tenant_id
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CsrfToken extends Model
{
    use HasFactory;

    protected $table = 'core_csrf_tokens';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'action',
        'scope',
        'ip_address',
        'user_agent_hash',
        'expires_at',
        'revoked_at',
        'last_verified_at',
        'rotation_count',
        'tenant_id',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'rotation_count' => 'integer',
        'metadata' => 'array',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', (string) $userId);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function markAsVerified(): void
    {
        $this->update(['last_verified_at' => now()]);
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
