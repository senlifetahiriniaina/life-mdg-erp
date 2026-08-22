<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockMovementFactory;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock_movements';

    public $timestamps = true;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'reference',
        'reason',
        'created_by',
    ];

    protected static function newFactory()
    {
        return StockMovementFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
