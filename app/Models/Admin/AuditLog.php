<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\User;
use Database\Factories\Admin\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'payload',
        'is_immutable',
        'data_expires_at',
        'archived_at',
        'signature',
    ];

    protected function casts(): array
    {
        return [
            'payload'         => 'array',
            'resource_id'     => 'integer',
            'is_immutable'    => 'boolean',
            'data_expires_at' => 'datetime',
            'archived_at'     => 'datetime',
        ];
    }

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an audit log entry.
     */
    public static function record(
        string $action,
        ?int $userId = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        mixed $payload = null,
    ): self {
        return static::create([
            'user_id'       => $userId ?? auth()->id(),
            'action'        => $action,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'payload'       => $payload,
        ]);
    }
}
