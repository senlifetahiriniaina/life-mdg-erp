<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\PickingOrderFactory;

/**
 * @property int $id
 * @property string $reference
 * @property int $warehouse_id
 * @property int|null $assigned_to
 * @property string $type
 * @property string $status
 * @property string $source_type
 * @property int|null $source_id
 * @property int $priority
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PickingOrder extends Model
{
    use HasFactory;

    protected static function newFactory(): PickingOrderFactory
    {
        return PickingOrderFactory::new();
    }

    protected $table = 'inventory_picking_orders';

    protected $fillable = [
        'reference',
        'warehouse_id',
        'assigned_to',
        'type',
        'status',
        'source_type',
        'source_id',
        'priority',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickingLine::class);
    }

    public function getProgressPercentAttribute(): int
    {
        $total = $this->lines->sum('quantity_requested');
        $picked = $this->lines->sum('quantity_picked');

        if ($total <= 0) {
            return 0;
        }

        return (int) round(($picked / $total) * 100);
    }
}
