<?php

declare(strict_types=1);

namespace Modules\API\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'api_keys';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'scopes',
        'rate_limit',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'description',
        'allowed_ips',
        'metadata',
    ];

    protected $casts = [
        'scopes'      => 'array',
        'allowed_ips' => 'array',
        'metadata'    => 'array',
        'last_used_at' => 'datetime',
        'expires_at'  => 'datetime',
        'revoked_at'  => 'datetime',
        'rate_limit'  => 'integer',
    ];

    protected $hidden = [
        'key_hash',
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApiRequest::class, 'api_key_id');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // --- Helpers ---

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function hasScope(string $scope): bool
    {
        if (empty($this->scopes)) {
            return false;
        }
        return in_array($scope, $this->scopes, true) || in_array('*', $this->scopes, true);
    }

    public function revoke(): bool
    {
        return $this->update(['revoked_at' => now()]);
    }

    public function touchLastUsed(): void
    {
        $this->timestamps = false;
        $this->update(['last_used_at' => now()]);
        $this->timestamps = true;
    }
}
