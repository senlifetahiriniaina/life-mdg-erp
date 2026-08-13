<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\CycleCountLineFactory;

/**
 * @property int $id
 * @property int $cycle_count_id
 * @property int $product_id
 * @property int|null $location_id
 * @property float $system_qty
 * @property float|null $counted_qty
 * @property float|null $variance
 * @property float|null $variance_value
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CycleCount $cycleCount
 * @property-read Product $product
 */
class CycleCountLine extends Model
{
    use HasFactory;

    protected static function newFactory(): CycleCountLineFactory
    {
        return CycleCountLineFactory::new();
    }

    protected $table = 'inventory_cycle_count_lines';

    protected $fillable = [
        'cycle_count_id',
        'product_id',
        'location_id',
        'system_qty',
        'counted_qty',
        'variance',
        'variance_value',
        'status',
    ];

    protected $casts = [
        'system_qty' => 'decimal:4',
        'counted_qty' => 'decimal:4',
        'variance' => 'float',
        'variance_value' => 'float',
    ];

    public function cycleCount(): BelongsTo
    {
        return $this->belongsTo(CycleCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
