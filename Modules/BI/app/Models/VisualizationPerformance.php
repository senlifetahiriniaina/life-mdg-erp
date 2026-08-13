<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $visualization_id
 * @property string               $render_time
 * @property int                  $data_points
 * @property string|null          $memory_usage
 * @property string|null          $cpu_usage
 * @property string               $status
 * @property string|null          $error_message
 * @property \Carbon\Carbon       $recorded_at
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read CustomVisualization $visualization
 */
class VisualizationPerformance extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_visualization_performance';

    protected $fillable = [
        'visualization_id',
        'render_time',
        'data_points',
        'memory_usage',
        'cpu_usage',
        'status',
        'error_message',
        'recorded_at',
    ];

    protected $casts = [
        'render_time'   => 'decimal:3',
        'memory_usage'  => 'decimal:2',
        'cpu_usage'     => 'decimal:2',
        'recorded_at'   => 'datetime',
    ];

    public function visualization(): BelongsTo
    {
        return $this->belongsTo(CustomVisualization::class, 'visualization_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function hasWarning(): bool
    {
        return $this->status === 'warning';
    }

    public function hasError(): bool
    {
        return $this->status === 'error';
    }
}
