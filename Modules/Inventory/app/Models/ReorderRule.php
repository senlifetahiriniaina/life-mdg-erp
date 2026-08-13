<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderRule extends Model
{
    use HasFactory;

    protected $table = 'inventory_reorder_rules';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'min_level',
        'max_level',
        'reorder_quantity',
        'lead_time_days',
        'status',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNeedsReorder($query)
    {
        return $query->where('status', 'active')
            ->join('inventory_products', 'inventory_reorder_rules.product_id', '=', 'inventory_products.id')
            ->whereRaw('inventory_products.quantity_on_hand <= inventory_reorder_rules.min_level');
    }
}
