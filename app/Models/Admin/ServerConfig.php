<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\User;
use Database\Factories\Admin\ServerConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ServerConfig extends Model
{
    use HasFactory;

    protected $table = 'admin_server_configs';

    protected $fillable = [
        'name',
        'provider',
        'region',
        'instance_type',
        'ip_address',
        'status',
        'credentials_encrypted',
        'api_endpoint',
        'metadata',
        'last_ping_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'     => 'array',
            'last_ping_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ServerConfigFactory
    {
        return ServerConfigFactory::new();
    }

    /**
     * Store credentials encrypted.
     */
    public function setCredentialsAttribute(mixed $value): void
    {
        $this->attributes['credentials_encrypted'] = $value !== null ? Crypt::encryptString((string) $value) : null;
    }

    /**
     * Decrypt credentials on read.
     */
    public function getCredentialsAttribute(): ?string
    {
        if ($this->attributes['credentials_encrypted'] === null) {
            return null;
        }
        try {
            return Crypt::decryptString((string) $this->attributes['credentials_encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }
}
