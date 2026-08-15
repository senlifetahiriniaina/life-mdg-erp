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
 * @property array<string,mixed>|null  $layout
 * @property int|null                  $owner_id
 * @property array<string>|null        $shared_with  role slugs
 * @property \Carbon\Carbon|null       $created_at
 * @property \Carbon\Carbon|null       $updated_at
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
        'layout',
        'owner_id',
        'shared_with',
    ];

    protected $casts = [
        'is_default'  => 'boolean',
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
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
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
