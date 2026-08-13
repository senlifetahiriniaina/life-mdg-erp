<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

/**
 * SecretAccessLog Model - Audit trail for secret access
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $secret_id
 * @property int $user_id
 * @property string $action
 * @property string|null $ip_address
 * @property bool $success
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $timestamp
 */
class SecretAccessLog extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'core_secret_access_logs';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'secret_id',
        'user_id',
        'action',
        'ip_address',
        'success',
        'reason',
        'timestamp',
    ];

    protected $casts = [
        'success' => 'boolean',
        'timestamp' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function secret(): BelongsTo
    {
        return $this->belongsTo(Secret::class, 'secret_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeBySecret($query, string $secretId)
    {
        return $query->where('secret_id', $secretId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeRecentFirst($query)
    {
        return $query->orderByDesc('timestamp');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->success === true;
    }

    public function isFailed(): bool
    {
        return $this->success === false;
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'retrieve' => 'Secret Retrieved',
            'create' => 'Secret Created',
            'rotate' => 'Secret Rotated',
            'revoke' => 'Secret Revoked',
            'access_denied' => 'Access Denied',
            default => 'Unknown Action',
        };
    }

    public function getStatusLabel(): string
    {
        return $this->success ? 'Success' : 'Failed';
    }
}
