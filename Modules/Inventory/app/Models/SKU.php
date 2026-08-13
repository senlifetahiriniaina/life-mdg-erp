<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\SKUFactory;

/**
 * Stock-Keeping Unit with per-warehouse stock derived from stock movements.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $unit
 * @property float $reorder_level
 * @property float $reorder_point
 * @property float $reorder_qty
 */
class SKU extends Model
{
    use HasFactory;

    protected $table = 'inventory_skus';

    protected $fillable = [
        'code',
        'name',
        'unit',
        'reorder_level',
        'reorder_point',
        'reorder_qty',
        'is_active',
    ];

    protected $casts = [
        'reorder_level' => 'float',
        'reorder_point' => 'float',
        'reorder_qty' => 'float',
        'is_active' => 'boolean',
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'sku_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * SKUs whose net total stock is at or below their reorder point.
     */
    public function scopeLowStock($query)
    {
        $ids = static::all()
            ->filter(fn (self $sku) => $sku->getTotalStock() <= (float) $sku->reorder_point)
            ->pluck('id');

        return $query->whereIn('id', $ids);
    }

    /**
     * Net stock for this SKU in a given warehouse (in movements minus out movements).
     */
    public function getStockInWarehouse($warehouse): float
    {
        $warehouseId = is_object($warehouse) ? $warehouse->id : $warehouse;

        $base = StockMovement::where('sku_id', $this->id)
            ->where('warehouse_id', $warehouseId);

        $in = (clone $base)->where('type', 'in')->sum('quantity');
        $out = (clone $base)->where('type', 'out')->sum('quantity');
        $adjustment = (clone $base)->where('type', 'adjustment')->sum('quantity');

        return (float) ($in - $out + $adjustment);
    }

    /**
     * Net total stock across all warehouses (in movements minus out movements).
     */
    public function getTotalStock(): float
    {
        $in = $this->stockMovements()->where('type', 'in')->sum('quantity');
        $out = $this->stockMovements()->where('type', 'out')->sum('quantity');
        $adjustment = $this->stockMovements()->where('type', 'adjustment')->sum('quantity');

        return (float) ($in - $out + $adjustment);
    }

    public function deactivate(): bool
    {
        $this->is_active = false;

        return $this->save();
    }

    protected static function newFactory(): SKUFactory
    {
        return SKUFactory::new();
    }
}
