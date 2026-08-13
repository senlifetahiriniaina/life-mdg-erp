<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Encryption\Encrypter;

/**
 * @property int              $id
 * @property int              $source_id
 * @property string           $credential_type
 * @property string           $encrypted_value
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read ExternalDataSource $source
 */
class ExternalCredential extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_external_credentials';

    protected $fillable = [
        'source_id',
        'credential_type',
        'encrypted_value',
        'expires_at',
    ];

    protected $hidden = ['encrypted_value'];

    protected $casts = [
        'expires_at'     => 'datetime',
        'last_used_at'   => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalDataSource::class, 'source_id');
    }

    public function getDecryptedValue(): string
    {
        return decrypt($this->encrypted_value);
    }

    public function setEncryptedValue(string $value): void
    {
        $this->encrypted_value = encrypt($value);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return now()->isAfter($this->expires_at);
    }

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function getCredentialTypeLabel(): string
    {
        return match ($this->credential_type) {
            'api_key'     => 'API Key',
            'oauth_token' => 'OAuth Token',
            'username'    => 'Username',
            'password'    => 'Password',
            'bearer_token' => 'Bearer Token',
            default       => ucfirst(str_replace('_', ' ', $this->credential_type)),
        };
    }
}
