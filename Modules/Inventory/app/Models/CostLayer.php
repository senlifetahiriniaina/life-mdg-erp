<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\CostLayerFactory;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $warehouse_id
 * @property string $method
 * @property string $quantity_received
 * @property string $quantity_remaining
 * @property string $unit_cost
 * @property string $total_cost
 * @property Carbon|null $received_at
 * @property string|null $reference
 * @property bool $is_exhausted
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CostLayer extends Model
{
    use HasFactory;

    protected static function newFactory(): CostLayerFactory
    {
        return CostLayerFactory::new();
    }

    protected $table = 'inventory_cost_layers';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'method',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'total_cost',
        'received_at',
        'reference',
        'is_exhausted',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'quantity_remaining' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'received_at' => 'datetime',
        'is_exhausted' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function isExhausted(): bool
    {
        return (bool) $this->is_exhausted;
    }

    public function consume(float $qty): void
    {
        $remaining = (float) $this->quantity_remaining - $qty;
        if ($remaining <= 0) {
            $this->update([
                'quantity_remaining' => 0,
                'is_exhausted' => true,
            ]);
        } else {
            $this->update(['quantity_remaining' => $remaining]);
        }
    }

    public function availableValue(): float
    {
        return (float) $this->quantity_remaining * (float) $this->unit_cost;
    }
}
