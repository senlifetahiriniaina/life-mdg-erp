<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                      $id
 * @property int                      $tenant_id
 * @property string                   $name
 * @property string|null              $description
 * @property string                   $query_type   sql|nl|graphql
 * @property string                   $query_text
 * @property array<mixed>|null        $last_result_preview
 * @property int|null                 $created_by
 * @property bool                     $is_shared
 * @property \Carbon\Carbon|null      $created_at
 * @property \Carbon\Carbon|null      $updated_at
 */
class SavedQuery extends Model
{
    use AuditableActions;
    protected $table = 'saved_queries';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'query_type',
        'query_text',
        'last_result_preview',
        'created_by',
        'is_shared',
    ];

    protected $casts = [
        'last_result_preview' => 'array',
        'is_shared'           => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->where('is_shared', true);
    }

    public function scopeVisibleTo(Builder $query, int $tenantId, int $userId): Builder
    {
        return $query->where('tenant_id', $tenantId)
            ->where(function (Builder $q) use ($userId) {
                $q->where('is_shared', true)
                  ->orWhere('created_by', $userId);
            });
    }
}
