<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dashboard_id
 * @property string $title
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property array<string, mixed>|null $position
 * @property int $refresh_interval
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Dashboard $dashboard
 */
class Widget extends Model
{
    use HasFactory;
    protected $table = 'bi_widgets';

    protected $fillable = [
        'dashboard_id',
        'title',
        'type',
        'config',
        'position',
        'refresh_interval',
    ];

    protected $casts = [
        'config' => 'array',
        'position' => 'array',
        'refresh_interval' => 'integer',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
