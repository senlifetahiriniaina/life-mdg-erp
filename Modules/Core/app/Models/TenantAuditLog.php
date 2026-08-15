<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Immutable audit trail for all superadmin/owner actions on a tenant.
 *
 * @property int         $id
 * @property string      $tenant_id
 * @property int|null    $user_id
 * @property string      $action      provision|suspend|reactivate|upgrade_plan|purge|step_completed|…
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property array|null  $old_values
 * @property array|null  $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class TenantAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'tenant_audit_log';

    /** No updates ever — append-only log. */
    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Factory Helper ───────────────────────────────────────────────────────

    /**
     * Insert a new audit log entry.  All parameters are optional except tenant_id and action.
     *
     * @param array<string, mixed> $options
     */
    public static function log(string $tenantId, string $action, array $options = []): self
    {
        $entry = new self([
            'tenant_id'   => $tenantId,
            'user_id'     => $options['user_id'] ?? (auth()->id() ?: null),
            'action'      => $action,
            'entity_type' => $options['entity_type'] ?? null,
            'entity_id'   => $options['entity_id'] ?? null,
            'old_values'  => $options['old_values'] ?? null,
            'new_values'  => $options['new_values'] ?? null,
            'ip_address'  => $options['ip_address'] ?? request()->ip(),
            'user_agent'  => $options['user_agent'] ?? request()->userAgent(),
            'created_at'  => now(),
        ]);

        $entry->save();

        return $entry;
    }
}
