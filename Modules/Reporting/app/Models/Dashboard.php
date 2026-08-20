<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int                       $id
 * @property int                       $tenant_id
 * @property string                    $name
 * @property string|null               $description
 * @property bool                      $is_default
 * @property bool                      $is_public
 * @property array<string,mixed>|null  $layout
 * @property int|null                  $created_by  dashboard owner (nullable — default/cloned-template dashboards are tenant-wide, not owned by one user)
 * @property array<string>|null        $shared_with  role slugs
 * @property \Carbon\Carbon|null       $created_at
 * @property \Carbon\Carbon|null       $updated_at
 *
 * Chantier 19 (Lot 5): $fillable used to include `owner_id`, a column that
 * has never existed on the real `dashboards` table (only `created_by` —
 * the exact same "who owns this" concept under its real, migrated name) —
 * a guaranteed "no such column" SQL error on every real create, confirmed
 * empirically. Repointed the model onto the real column instead of adding a
 * redundant duplicate; `shared_with` (a genuinely different, real-value
 * concept) got a real migration instead.
 */
class Dashboard extends Model
{
    use HasFactory;
    use AuditableActions;
    protected $table = 'dashboards';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_default',
        'is_public',
        'layout',
        'created_by',
        'shared_with',
    ];

    protected $casts = [
        'is_default'  => 'boolean',
        'is_public'   => 'boolean',
        'layout'      => 'array',
        'shared_with' => 'array',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function widgets(): HasMany
    {
        return $this->hasMany(ReportWidget::class, 'dashboard_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isSharedWith(string $roleSlug): bool
    {
        return in_array($roleSlug, $this->shared_with ?? [], true);
    }
}
