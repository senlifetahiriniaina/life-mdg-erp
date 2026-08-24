<?php

declare(strict_types=1);

namespace Modules\API\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Modules\Core\Traits\RecordsActivity;

class ApiKey extends Model
{
    use HasFactory;
    // Chantier 32.5 (layer 11 — CORE integration): creating/revoking an
    // external API credential is exactly the class of sensitive action the
    // rest of this app's audit trail (54 models via RecordsActivity, 23 via
    // AuditableActions) already covers — this model had neither. Safe to
    // add: RecordsActivity::toAuditArray() defaults to $this->toArray(),
    // which already respects $hidden below, so key_hash is never written
    // to core_audit_logs either.
    use RecordsActivity;

    protected static string $auditModule = 'API';

    /**
     * Chantier 32.5: found empirically, not by reading the trait — without
     * this, every single authenticated request through
     * AuthenticateApiKey's touchLastUsed() call (an instance ->update(),
     * needed so RecordsActivity's own create/revoke logging fires at all —
     * see that method's own comment) also wrote its own "updated" entry to
     * core_audit_logs, since last_used_at changing on every request always
     * counted as a real change. That would have made every single real API
     * call double-write to core_audit_logs in addition to api_requests —
     * confirmed by tinker: 3 successful pings produced 3 extra audit
     * entries before this fix. Scoping the diff to the fields a human
     * actually cares about (create/rename/rescope/revoke) means a
     * last_used_at-only update no longer counts as a change worth logging.
     */
    protected static array $auditableFields = [
        'name', 'scopes', 'rate_limit', 'expires_at', 'revoked_at', 'description',
    ];

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
