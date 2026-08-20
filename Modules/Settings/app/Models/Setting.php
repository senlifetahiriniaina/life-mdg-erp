<?php

declare(strict_types=1);

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int                             $id
 * @property int|null                        $tenant_id
 * @property string                          $module
 * @property string                          $key
 * @property string|null                     $value
 * @property string                          $value_type
 * @property string|null                     $description
 * @property bool                            $is_public
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
class Setting extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'settings';

    protected $fillable = [
        'tenant_id',
        'module',
        'key',
        'value',
        'value_type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    // -----------------------------------------------------------------------
    // Global scopes
    // -----------------------------------------------------------------------

    /**
     * Boot tenant scope: automatically filter by the authenticated user's
     * company_id when present, but allow null tenant_id rows through as well
     * (global settings).
     *
     * Chantier 19 Lot 3: this and the 3 sibling methods below (get()/set()/
     * SettingsService::currentTenantId()) all resolved the tenant boundary
     * as `auth()?->user()?->company_id ?? request()?->header('X-Company-ID')`
     * — the exact client-controlled-header IDOR pattern already fixed for
     * Setup's identical vulnerability in Chantier 8.5sv ("since company_id
     * is commonly null for ordinary users, the client-controlled header
     * fallback was reached in the common case"). Any authenticated user
     * whose own company_id is null (an unprovisioned/newly-registered
     * account) could set `X-Company-ID: <victim>` to read or write another
     * company's settings. This scope had a second, independent bug on top
     * of that: when no tenant id resolved at all (no header, no real
     * company_id), the `if ($tenantId)` guard skipped adding any filter
     * whatsoever, returning every tenant's every setting completely
     * unfiltered to such a caller — worse than the header override, since
     * it needed no attacker action at all. Fixed to drop the header
     * fallback entirely and to always filter to tenant-or-global rows
     * (falling back to global-only when there is no real tenant), matching
     * the already-correct pattern SettingsService::getModule() uses inline.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = auth()?->user()?->company_id;

            $builder->where(function (Builder $q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            });
        });

        static::creating(function (self $model) {
            if ($model->tenant_id === null && auth()?->user()?->company_id) {
                $model->tenant_id = auth()->user()->company_id;
            }
        });
    }

    // -----------------------------------------------------------------------
    // Value casting helpers
    // -----------------------------------------------------------------------

    /**
     * Return the typed, decoded value.
     */
    public function getCastedValue(): mixed
    {
        return match ($this->value_type) {
            'integer'   => (int) $this->value,
            'boolean'   => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json'      => json_decode((string) $this->value, true),
            'encrypted' => $this->value !== null ? Crypt::decryptString($this->value) : null,
            default     => $this->value,
        };
    }

    /**
     * Encode a raw PHP value into a string for storage according to value_type.
     */
    public function encodeValue(mixed $raw): string
    {
        return match ($this->value_type) {
            'boolean'   => $raw ? '1' : '0',
            'json'      => json_encode($raw, JSON_THROW_ON_ERROR),
            'encrypted' => Crypt::encryptString((string) $raw),
            default     => (string) $raw,
        };
    }

    // -----------------------------------------------------------------------
    // Static convenience helpers
    // -----------------------------------------------------------------------

    /**
     * Retrieve a setting value (typed), falling back to $default when not found.
     */
    public static function get(string $module, string $key, mixed $default = null): mixed
    {
        $setting = static::withoutGlobalScope('tenant')
            ->where('module', $module)
            ->where('key', $key)
            ->where(function (Builder $q) {
                // Chantier 19 Lot 3: dropped the client-controlled
                // X-Company-ID header fallback — see boot()'s docblock
                // above for the full rationale.
                $tenantId = auth()?->user()?->company_id;
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->orderByRaw('tenant_id IS NULL ASC') // tenant-specific first
            ->first();

        if ($setting === null) {
            return $default;
        }

        return $setting->getCastedValue();
    }

    /**
     * Persist (upsert) a setting for the current tenant.
     */
    public static function set(string $module, string $key, mixed $value): void
    {
        // Chantier 19 Lot 3: dropped the client-controlled X-Company-ID
        // header fallback — see boot()'s docblock above for the full
        // rationale. This is the write path, so it was the more severe of
        // the two static-method instances of this bug.
        $tenantId  = auth()?->user()?->company_id;
        $valueType = match (true) {
            is_bool($value)  => 'boolean',
            is_int($value)   => 'integer',
            is_array($value) => 'json',
            default          => 'string',
        };

        $raw = match ($valueType) {
            'boolean' => $value ? '1' : '0',
            'json'    => json_encode($value, JSON_THROW_ON_ERROR),
            default   => (string) $value,
        };

        static::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'module' => $module, 'key' => $key],
            ['value' => $raw, 'value_type' => $valueType]
        );
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /** @param Builder<self> $query */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /** @param Builder<self> $query */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** @param Builder<self> $query */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant')->whereNull('tenant_id');
    }
}
