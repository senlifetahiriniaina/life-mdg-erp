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
 * @property string                    $name
 * @property string|null               $description
 * @property string                    $chart_type
 * @property array<string, mixed>      $default_config
 * @property array<string, mixed>|null $color_scheme
 * @property bool                      $is_public
 * @property int                       $usage_count
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\User     $creator
 * @property-read \App\Models\Company  $company
 */
class VisualizationTemplate extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_visualization_templates';

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'chart_type',
        'default_config',
        'color_scheme',
        'is_public',
    ];

    protected $casts = [
        'default_config' => 'array',
        'color_scheme'   => 'array',
        'is_public'      => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function visualizations(): HasMany
    {
        return $this->hasMany(CustomVisualization::class, 'template_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }

    public function getChartType(): string
    {
        return $this->chart_type;
    }

    public function getColorScheme(): array
    {
        return $this->color_scheme ?? [];
    }
}
