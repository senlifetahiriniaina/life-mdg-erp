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
     */
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = auth()?->user()?->company_id ?? request()?->header('X-Company-ID');

            if ($tenantId) {
                $builder->where(function (Builder $q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->orWhereNull('tenant_id');
                });
            }
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
                $tenantId = auth()?->user()?->company_id ?? request()?->header('X-Company-ID');
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
        $tenantId  = auth()?->user()?->company_id ?? request()?->header('X-Company-ID');
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
