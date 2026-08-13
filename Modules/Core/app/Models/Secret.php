<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;

/**
 * Secret Model - Encrypted secrets management
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $type
 * @property string $encrypted_value
 * @property int $key_version
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $rotated_at
 * @property \Illuminate\Support\Carbon|null $next_rotation
 * @property bool $is_active
 * @property array|null $tags
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Secret extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'core_secrets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'type',
        'encrypted_value',
        'key_version',
        'created_by',
        'expires_at',
        'rotated_at',
        'next_rotation',
        'is_active',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'expires_at' => 'datetime',
        'rotated_at' => 'datetime',
        'next_rotation' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(SecretAccessLog::class, 'secret_id');
    }

    public function rotationPolicy(): HasOne
    {
        return $this->hasOne(SecretRotationPolicy::class, 'secret_id');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(SecretAccessGrant::class, 'secret_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeDueForRotation($query)
    {
        return $query->where('next_rotation', '<', now())->active();
    }

    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < now();
    }

    public function daysUntilExpiration(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at);
    }

    public function daysUntilRotation(): ?int
    {
        if ($this->next_rotation === null) {
            return null;
        }

        return (int) now()->diffInDays($this->next_rotation);
    }

    public function isDueForRotation(): bool
    {
        return $this->next_rotation !== null && $this->next_rotation < now();
    }

    public function getSecretType(): string
    {
        return match ($this->type) {
            'api_key' => 'API Key',
            'oauth_token' => 'OAuth Token',
            'database_credential' => 'Database Credential',
            'ssh_key' => 'SSH Key',
            'certificate' => 'Certificate',
            default => 'Unknown',
        };
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $this->update(['tags' => array_values(array_diff($tags, [$tag]))]);
    }
}
