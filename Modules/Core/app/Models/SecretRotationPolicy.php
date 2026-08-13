<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SecretRotationPolicy Model - Rotation configuration per secret
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $secret_id
 * @property int $rotation_interval
 * @property \Illuminate\Support\Carbon|null $last_rotation_at
 * @property \Illuminate\Support\Carbon|null $next_rotation_at
 * @property bool $auto_rotate
 * @property array $notification_days_before
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SecretRotationPolicy extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'core_secret_rotation_policies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'secret_id',
        'rotation_interval',
        'last_rotation_at',
        'next_rotation_at',
        'auto_rotate',
        'notification_days_before',
    ];

    protected $casts = [
        'notification_days_before' => 'array',
        'last_rotation_at' => 'datetime',
        'next_rotation_at' => 'datetime',
        'auto_rotate' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function secret(): BelongsTo
    {
        return $this->belongsTo(Secret::class, 'secret_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeDueForRotation($query)
    {
        return $query->where('next_rotation_at', '<', now())->where('auto_rotate', true);
    }

    public function scopeAutoRotate($query)
    {
        return $query->where('auto_rotate', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isDueForRotation(): bool
    {
        return $this->auto_rotate && $this->next_rotation_at !== null && $this->next_rotation_at < now();
    }

    public function daysUntilRotation(): ?int
    {
        if ($this->next_rotation_at === null) {
            return null;
        }

        $days = now()->diffInDays($this->next_rotation_at);
        return $days < 0 ? null : $days;
    }

    public function shouldNotify(): array
    {
        $notificationDays = $this->notification_days_before ?? [];
        $upcomingNotifications = [];

        foreach ($notificationDays as $daysAhead) {
            $notificationDate = now()->addDays($daysAhead);
            if ($this->next_rotation_at !== null
                && $this->next_rotation_at->between($notificationDate, $notificationDate->addDay())) {
                $upcomingNotifications[] = $daysAhead;
            }
        }

        return $upcomingNotifications;
    }

    public function setNextRotation(): void
    {
        $this->next_rotation_at = now()->addDays($this->rotation_interval);
        $this->save();
    }

    public function markRotated(): void
    {
        $this->last_rotation_at = now();
        $this->setNextRotation();
    }
}
