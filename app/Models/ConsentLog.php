<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentLog extends Model
{
    protected $fillable = [
        'user_id',
        'consent_type',
        'granted',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recordConsent(
        ?int $userId,
        string $consentType,
        bool $granted,
        ?int $expiryDays = 365
    ): self {
        return self::create([
            'user_id' => $userId,
            'consent_type' => $consentType,
            'granted' => $granted,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => $expiryDays ? now()->addDays($expiryDays) : null,
        ]);
    }

    public static function hasConsent(string $consentType, ?int $userId = null): bool
    {
        return self::where('consent_type', $consentType)
            ->where('granted', true)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->exists();
    }

    public static function withdrawConsent(string $consentType, int $userId): bool
    {
        return (bool) self::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->update(['granted' => false]);
    }
}
