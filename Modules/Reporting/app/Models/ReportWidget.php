<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int                      $id
 * @property int                      $tenant_id
 * @property int                      $dashboard_id
 * @property string                   $widget_type
 * @property string                   $title
 * @property array<string,mixed>      $data_source   {module, query, params}
 * @property array<string,mixed>|null $config        {colors, labels, thresholds}
 * @property int                      $position_x
 * @property int                      $position_y
 * @property int                      $width         1-4
 * @property int                      $height        1-3
 * @property int                      $refresh_interval_seconds
 * @property \Carbon\Carbon|null      $created_at
 * @property \Carbon\Carbon|null      $updated_at
 */
class ReportWidget extends Model
{
    use HasFactory;
    use AuditableActions;
    protected $table = 'report_widgets';

    protected $fillable = [
        'tenant_id',
        'dashboard_id',
        'widget_type',
        'title',
        'data_source',
        'config',
        'position_x',
        'position_y',
        'width',
        'height',
        'refresh_interval_seconds',
    ];

    protected $casts = [
        'data_source' => 'array',
        'config'      => 'array',
        'position_x'  => 'integer',
        'position_y'  => 'integer',
        'width'        => 'integer',
        'height'       => 'integer',
        'refresh_interval_seconds' => 'integer',
    ];

    public const WIDGET_TYPES = [
        'kpi_card', 'line_chart', 'bar_chart', 'pie_chart',
        'table', 'heatmap', 'gauge', 'funnel', 'cohort',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class, 'dashboard_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForDashboard(Builder $query, int $dashboardId): Builder
    {
        return $query->where('dashboard_id', $dashboardId);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function getModule(): string
    {
        return $this->data_source['module'] ?? 'Reporting';
    }

    public function getQuery(): string
    {
        return $this->data_source['query'] ?? '';
    }
}
