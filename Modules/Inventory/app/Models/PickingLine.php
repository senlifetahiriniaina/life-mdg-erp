<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\PickingLineFactory;

/**
 * @property int $id
 * @property int $picking_order_id
 * @property int $product_id
 * @property int|null $location_id
 * @property float $quantity_requested
 * @property float $quantity_picked
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PickingOrder $pickingOrder
 * @property-read Product $product
 */
class PickingLine extends Model
{
    use HasFactory;

    protected static function newFactory(): PickingLineFactory
    {
        return PickingLineFactory::new();
    }

    protected $table = 'inventory_picking_lines';

    protected $fillable = [
        'picking_order_id',
        'product_id',
        'location_id',
        'quantity_requested',
        'quantity_picked',
        'status',
    ];

    protected $casts = [
        'quantity_requested' => 'float',
        'quantity_picked' => 'float',
    ];

    public function pickingOrder(): BelongsTo
    {
        return $this->belongsTo(PickingOrder::class);
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
