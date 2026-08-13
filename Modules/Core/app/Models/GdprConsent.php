<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\GdprConsentFactory;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $consent_type
 * @property bool $granted
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GdprConsent extends Model
{
    use HasFactory;

    protected $table = 'core_gdpr_consents';

    protected $fillable = [
        'user_id',
        'email',
        'consent_type',
        'granted',
        'granted_at',
        'revoked_at',
        'ip_address',
        'user_agent',
        'source',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function newFactory(): GdprConsentFactory
    {
        return GdprConsentFactory::new();
    }

    public function isGranted(): bool
    {
        return $this->granted && $this->revoked_at === null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function grant(): void
    {
        $this->update([
            'granted' => true,
            'granted_at' => now(),
            'revoked_at' => null,
        ]);
    }

    public function revoke(): void
    {
        $this->update([
            'granted' => false,
            'revoked_at' => now(),
        ]);
    }
}
