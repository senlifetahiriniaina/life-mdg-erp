<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Phase 40 — Full Tenant Model (multi-tenant portal).
 *
 * Represents a company/organisation that has signed up for WideHalo ERP.
 * Each tenant owns an isolated database (db_name/db_host/db_port).
 *
 * @property string      $id
 * @property string      $uuid
 * @property string|null $slug                  Subdomain: {slug}.widehalo.com
 * @property string|null $name
 * @property string|null $company_name
 * @property string|null $legal_name
 * @property string|null $company_type          sarl|sa|sas|snc|cooperative|ngo|individual
 * @property string|null $country_code          ISO alpha-2
 * @property string|null $region
 * @property string|null $city
 * @property string|null $currency              ISO 4217
 * @property string|null $timezone
 * @property string|null $locale                fr|en|ar|sw|mg|ha|zh|hi
 * @property string|null $industry
 * @property string      $plan                  starter|professional|enterprise|custom
 * @property Carbon|null $plan_expires_at
 * @property string      $status                trial|active|suspended|cancelled
 * @property Carbon|null $trial_ends_at
 * @property string|null $db_name
 * @property string|null $db_host
 * @property int|null    $db_port
 * @property array|null  $settings
 * @property int         $onboarding_step       0-5
 * @property Carbon|null $onboarding_completed_at
 * @property int|null    $owner_id
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $logo_url
 * @property string|null $primary_color
 * @property bool        $is_active
 * @property string|null $domain
 * @property array|null  $data                  Legacy stancl/tenancy data column
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Primary key is a 25-char alphanumeric string (legacy format). */
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'uuid',
        'slug',
        'name',
        'company_name',
        'legal_name',
        'company_type',
        'country_code',
        'region',
        'city',
        'currency',
        'timezone',
        'locale',
        'industry',
        'plan',
        'plan_expires_at',
        'status',
        'trial_ends_at',
        'db_name',
        'db_host',
        'db_port',
        'settings',
        'onboarding_step',
        'onboarding_completed_at',
        'owner_id',
        'contact_email',
        'contact_phone',
        'logo_url',
        'primary_color',
        'is_active',
        'domain',
        'data',
    ];

    protected $casts = [
        'settings'               => 'array',
        'data'                   => 'array',
        'plan_expires_at'        => 'datetime',
        'trial_ends_at'          => 'datetime',
        'onboarding_completed_at'=> 'datetime',
        'is_active'              => 'boolean',
        'db_port'                => 'integer',
        'onboarding_step'        => 'integer',
    ];

    protected $hidden = [
        'db_name',
        'db_host',
        'db_port',
        'data',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The user who owns / registered this tenant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * All users who have been granted access to this tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users', 'tenant_id', 'user_id')
            ->withPivot(['role', 'joined_at', 'invited_by'])
            ->withTimestamps();
    }

    /**
     * Pending / accepted invitations for this tenant.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class, 'tenant_id');
    }

    /**
     * Audit trail of all actions performed on this tenant.
     */
    public function auditLog(): HasMany
    {
        return $this->hasMany(TenantAuditLog::class, 'tenant_id');
    }

    /**
     * Enabled/disabled modules for this tenant.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class, 'tenant_id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Computed / Virtual Attributes
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return the full subdomain for this tenant.
     * e.g.  acme-a3b7kxmn.widehalo.com
     */
    public function getSubdomainAttribute(): string
    {
        return ($this->slug ?? $this->id) . '.widehalo.com';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Business Logic Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Is this tenant in a usable state (not suspended or cancelled)?
     */
    public function isActive(): bool
    {
        if (isset($this->attributes['status'])) {
            return in_array($this->status, ['active', 'trial'], true);
        }

        // Legacy is_active flag fallback
        return (bool) ($this->attributes['is_active'] ?? true);
    }

    /**
     * Is the tenant currently on a free trial?
     */
    public function isOnTrial(): bool
    {
        if (isset($this->attributes['status'])) {
            return $this->status === 'trial';
        }

        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Number of calendar days until the trial expires.
     * Returns 0 if the trial has already ended.
     */
    public function daysUntilTrialExpiry(): int
    {
        if ($this->trial_ends_at === null) {
            return 0;
        }

        $days = (int) now()->diffInDays($this->trial_ends_at, false);

        return max(0, $days);
    }

    /**
     * Check whether a specific feature flag is enabled in the settings JSON.
     *
     * Usage: $tenant->hasFeature('mobile_money')
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->settings['features'] ?? [];

        return (bool) ($features[$feature] ?? false);
    }

    /**
     * Return the database connection configuration array for this tenant's
     * isolated database.  Merges over the default 'mysql' config.
     *
     * @return array<string, mixed>
     */
    public function getConnectionConfig(): array
    {
        $default = config('database.connections.mysql', []);

        return array_merge($default, array_filter([
            'database' => $this->db_name,
            'host'     => $this->db_host,
            'port'     => $this->db_port,
        ]));
    }
}
