<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $report_id
 * @property int    $shared_with_user_id
 * @property string $permission  view|edit
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ReportShare extends Model
{
    use AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'report_shares';

    protected $fillable = [
        'report_id',
        'shared_with_user_id',
        'permission',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_id');
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'shared_with_user_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Shares visible via reports belonging to the given tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->whereHas('report', function (Builder $q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });
    }

    /**
     * Shares where the given user is the recipient.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('shared_with_user_id', $userId);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function canEdit(): bool
    {
        return $this->permission === 'edit';
    }
}
