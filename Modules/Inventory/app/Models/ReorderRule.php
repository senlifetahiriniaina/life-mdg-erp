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
        'company_id',
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

    /**
     * Chantier 17c — was joining `inventory_products.quantity_on_hand`, a
     * column that has never existed on that table (confirmed via
     * Schema::hasColumn — stock quantity lives per-warehouse on
     * `inventory_stock`, not as a flat column on Product) — a guaranteed
     * SQL error the moment this scope was ever actually called (it wasn't,
     * anywhere in the app, until now). Fixed to join the real per-(product,
     * warehouse) stock row this rule is scoped to, matching what
     * `warehouse_id` on this model is for in the first place.
     */
    public function scopeNeedsReorder($query)
    {
        return $query->where('status', 'active')
            ->join('inventory_stock', function ($join) {
                $join->on('inventory_reorder_rules.product_id', '=', 'inventory_stock.product_id')
                    ->on('inventory_reorder_rules.warehouse_id', '=', 'inventory_stock.warehouse_id');
            })
            ->whereColumn('inventory_stock.quantity', '<=', 'inventory_reorder_rules.min_level');
    }
}
