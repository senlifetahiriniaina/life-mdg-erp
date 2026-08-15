<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string|null $avatar
 * @property string $password
 * @property string $locale
 * @property string $timezone
 * @property bool $is_active
 * @property string|null $google2fa_secret
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property bool $cookie_consent
 * @property bool $marketing_consent
 * @property \Illuminate\Support\Carbon|null $cookie_consent_at
 * @property \Illuminate\Support\Carbon|null $marketing_consent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $remember_token
 * @property-read string $full_name
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'company_id', 'role',
        'name', 'first_name', 'last_name', 'email', 'phone', 'avatar',
        'password', 'locale', 'timezone', 'is_active',
        'google2fa_secret', 'two_factor_enabled', 'two_factor_confirmed_at',
        'two_factor_recovery_codes', 'failed_login_attempts', 'locked_until',
        'last_login_at', 'last_login_ip',
        'cookie_consent', 'marketing_consent', 'cookie_consent_at', 'marketing_consent_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google2fa_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Number of consecutive failed logins that triggers a temporary lock,
     * and how long (minutes) the account stays locked.
     */
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOCKOUT_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'locked_until' => 'datetime',
            'cookie_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'cookie_consent_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->name;
    }

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    */

    /** Whether the user has completed 2FA enrollment (secret confirmed). */
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled
            && ! empty($this->google2fa_secret)
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Whether 2FA is mandatory for this user. Config-driven so a role can be
     * added without a deploy — see config/security.php's mandatory_2fa_roles
     * for the default (super-admin, admin) and rollout caveats.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(config('security.mandatory_2fa_roles', ['super-admin', 'admin']));
    }

    /*
    |--------------------------------------------------------------------------
    | Brute-force account lockout (OWASP A07)
    |--------------------------------------------------------------------------
    */

    public function isAccountLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function recordFailedLoginAttempt(): void
    {
        $attempts = (int) $this->failed_login_attempts + 1;
        $attributes = ['failed_login_attempts' => $attempts];

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $attributes['locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $this->forceFill($attributes)->save();
    }

    public function resetLoginAttempts(): void
    {
        if ($this->failed_login_attempts === 0 && $this->locked_until === null) {
            return;
        }

        $this->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }

    public function getLockedUntilFormatted(): ?string
    {
        return $this->locked_until?->toIso8601String();
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\Modules\HR\Models\Employee::class, 'user_id');
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
