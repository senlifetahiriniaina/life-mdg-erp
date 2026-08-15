<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

/**
 * SecretAccessGrant Model - Per-user access to secrets
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $secret_id
 * @property int $user_id
 * @property array $scopes
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property int $granted_by
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SecretAccessGrant extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'core_secret_access_grants';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'secret_id',
        'user_id',
        'scopes',
        'expires_at',
        'revoked_at',
        'granted_by',
        'reason',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function secret(): BelongsTo
    {
        return $this->belongsTo(Secret::class, 'secret_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySecret($query, string $secretId)
    {
        return $query->where('secret_id', $secretId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at < now()) {
            return false;
        }

        return true;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function canRead(): bool
    {
        return $this->hasScope('read') && $this->isActive();
    }

    public function canRotate(): bool
    {
        return $this->hasScope('rotate') && $this->isActive();
    }

    public function canRevoke(): bool
    {
        return $this->hasScope('revoke') && $this->isActive();
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function daysUntilExpiration(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        // See ApiKey::daysUntilExpiration() — diffInDays() returns a
        // float, so a plain (int) cast floors and can under-report by
        // one day purely from wall-clock drift since expires_at was set.
        return (int) ceil(now()->diffInDays($this->expires_at));
    }
}
