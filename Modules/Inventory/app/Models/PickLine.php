<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\PickLineFactory;

/**
 * @property int $id
 * @property int $wave_id
 * @property int $product_id
 * @property string|null $warehouse_location
 * @property string $qty_requested
 * @property string $qty_picked
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PickingWave $wave
 * @property-read Product $product
 */
class PickLine extends Model
{
    use HasFactory;

    protected static function newFactory(): PickLineFactory
    {
        return PickLineFactory::new();
    }

    protected $table = 'inventory_pick_lines';

    protected $fillable = [
        'wave_id',
        'product_id',
        'warehouse_location',
        'qty_requested',
        'qty_picked',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'qty_requested' => 'decimal:2',
        'qty_picked' => 'decimal:2',
    ];

    /** @return BelongsTo<PickingWave, self> */
    public function wave(): BelongsTo
    {
        return $this->belongsTo(PickingWave::class, 'wave_id');
    }

    /** @return BelongsTo<Product, self> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
