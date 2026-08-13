<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                       $id
 * @property int                       $company_id
 * @property int                       $created_by
 * @property int|null                  $dashboard_id
 * @property int|null                  $template_id
 * @property string                    $name
 * @property string|null               $description
 * @property string                    $type
 * @property array<string, mixed>      $config
 * @property array<string, mixed>      $data_source
 * @property array<string, mixed>|null $color_scale
 * @property array<string, mixed>|null $range_config
 * @property bool                      $real_time_enabled
 * @property int                       $refresh_interval
 * @property int|null                  $performance_score
 * @property string|null               $avg_render_time
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\User     $creator
 * @property-read \App\Models\Company  $company
 * @property-read Dashboard|null       $dashboard
 * @property-read VisualizationTemplate|null $template
 */
class CustomVisualization extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_custom_visualizations';

    protected $fillable = [
        'company_id',
        'created_by',
        'dashboard_id',
        'template_id',
        'name',
        'description',
        'type',
        'config',
        'data_source',
        'color_scale',
        'range_config',
        'real_time_enabled',
        'refresh_interval',
    ];

    protected $casts = [
        'config'              => 'array',
        'data_source'         => 'array',
        'color_scale'         => 'array',
        'range_config'        => 'array',
        'real_time_enabled'   => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VisualizationTemplate::class, 'template_id');
    }

    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(VisualizationPerformance::class, 'visualization_id');
    }

    public function updatePerformanceScore(): void
    {
        $recentMetrics = $this->performanceMetrics()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->get();

        if ($recentMetrics->isEmpty()) {
            return;
        }

        $successCount = $recentMetrics->where('status', 'success')->count();
        $score = (int) (($successCount / $recentMetrics->count()) * 100);
        $avgRenderTime = $recentMetrics->avg('render_time');

        $this->update([
            'performance_score'   => $score,
            'avg_render_time'     => $avgRenderTime,
        ]);
    }

    public function enableRealTime(): void
    {
        $this->update(['real_time_enabled' => true]);
    }

    public function disableRealTime(): void
    {
        $this->update(['real_time_enabled' => false]);
    }
}
