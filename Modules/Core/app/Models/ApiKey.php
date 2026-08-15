<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

/**
 * ApiKey Model - Service account API keys with scopes
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $user_id
 * @property string $name
 * @property string $key_hash
 * @property string $key_preview
 * @property array $scopes
 * @property string|null $ip_restrictions
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ApiKey extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'core_api_keys';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'name',
        'key_hash',
        'key_preview',
        'scopes',
        'ip_restrictions',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['key_hash'];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at < now()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < now();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function hasAllScopes(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (!$this->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }

    public function hasAnyScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    public function checkIpRestriction(string $ip): bool
    {
        if ($this->ip_restrictions === null) {
            return true;
        }

        $allowedIps = explode(',', $this->ip_restrictions);
        return in_array($ip, array_map('trim', $allowedIps), true);
    }

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function daysUntilExpiration(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        // diffInDays() returns a float; a plain (int) cast truncates
        // toward zero, which floors positive values — a few ms of
        // elapsed wall-clock time turns a fresh "30 days" grant into 29.
        // ceil() rounds any partial day still remaining up to a whole
        // day, matching the "N days left" semantics callers expect.
        return (int) ceil(now()->diffInDays($this->expires_at));
    }
}
