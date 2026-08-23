<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\BelongsToTenant;
use Modules\Core\Traits\RecordsActivity;
use Modules\Helpdesk\Traits\HelpdeskLinkable;
use Modules\Inventory\Database\Factories\ProductFactory;

class Product extends Model
{
    use BelongsToTenant, HasFactory, HelpdeskLinkable, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Inventory';

    protected $table = 'inventory_products';

    protected $fillable = [
        'company_id',
        'category_id',
        'unit_id',
        'sku',
        'name',
        'barcode',
        'description',
        'type',
        'cost_price',
        'sale_price',
        'selling_price',
        'currency',
        'category',
        'unit',
        'status',
        'reorder_level',
        'tenant_id',
        'reorder_point',
        'reorder_qty',
        'valuation_method',
        'track_serial',
        'track_lot',
        'image',
        'is_active',
        'attributes',
        'ecommerce_synced_at',
        'ecommerce_sync_pending',
        'company_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'track_serial' => 'boolean',
        'track_lot' => 'boolean',
        'is_active' => 'boolean',
        'attributes' => 'json',
        'ecommerce_synced_at' => 'datetime',
        'ecommerce_sync_pending' => 'boolean',
    ];

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function reorderRules()
    {
        return $this->hasMany(ReorderRule::class, 'product_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Supplier::class, 'supplier_id');
    }

    public function scopeLowStock($query)
    {
        return $query->join('inventory_stock', 'inventory_products.id', '=', 'inventory_stock.product_id')
            ->whereRaw('inventory_stock.quantity < inventory_products.reorder_point');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        $stock = $this->stock()->sum('quantity') ?? 0;

        return $stock < $this->reorder_point;
    }

    public function getMarginPercent(): float
    {
        if ($this->cost_price == 0) {
            return 0;
        }

        return (($this->sale_price - $this->cost_price) / $this->cost_price) * 100;
    }

    public function stock()
    {
        return $this->hasMany(Stock::class, 'product_id');
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class, 'tenant_id');
    }
}
