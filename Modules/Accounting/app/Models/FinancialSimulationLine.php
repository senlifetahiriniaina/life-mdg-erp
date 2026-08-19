<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Accounting\Database\Factories\FinancialSimulationLineFactory;
use Modules\Inventory\Models\Product;

class FinancialSimulationLine extends Model
{
    use HasFactory;

    protected $table = 'acc_financial_simulation_lines';

    protected $fillable = [
        'financial_simulation_id',
        'type',
        'product_id',
        'supplier_id',
        'contact_id',
        'label',
        'quantity',
        'unit_price',
        'recurrence',
        'start_date',
        'end_date',
        'growth_rate_percent',
        'counterpart_account_code',
        'status',
        'realized_at',
        'realized_type',
        'realized_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'growth_rate_percent' => 'decimal:4',
        'start_date' => 'date',
        'end_date' => 'date',
        'realized_at' => 'datetime',
    ];

    protected static function newFactory(): FinancialSimulationLineFactory
    {
        return FinancialSimulationLineFactory::new();
    }

    public function simulation(): BelongsTo
    {
        return $this->belongsTo(FinancialSimulation::class, 'financial_simulation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\Modules\Achats\Models\Supplier::class, 'supplier_id');
    }

    public function realizedRecord(): MorphTo
    {
        return $this->morphTo('realized', 'realized_type', 'realized_id');
    }

    public function isSale(): bool
    {
        return $this->type === 'sale';
    }

    public function isPurchase(): bool
    {
        return $this->type === 'purchase';
    }

    /**
     * Effective unit price: explicit override, else the linked product's
     * selling price (sale lines) / cost price (purchase lines).
     */
    public function effectiveUnitPrice(): float
    {
        if ($this->unit_price !== null) {
            return (float) $this->unit_price;
        }

        if (! $this->product) {
            return 0.0;
        }

        return $this->isSale()
            ? (float) ($this->product->selling_price ?? $this->product->sale_price ?? 0)
            : (float) ($this->product->cost_price ?? 0);
    }

    /**
     * Counterpart chart-of-account code: explicit override, else derived
     * from the linked product's category (Chantier 17's
     * default_sale_account_code/default_purchase_account_code).
     *
     * Chantier 18: `$product->category` (magic attribute access) resolves
     * to `inventory_products.category` — a plain display *string* column,
     * not the `category()` relation (the string attribute wins over a
     * same-named relation method, a quirk already documented in Chantier
     * 16) — calling `?->default_sale_account_code` on it would fatal.
     * Calling the relation method explicitly avoids the collision.
     */
    public function effectiveCounterpartAccountCode(): ?string
    {
        if ($this->counterpart_account_code) {
            return $this->counterpart_account_code;
        }

        $category = $this->product ? $this->product->category()->first() : null;

        if (! $category) {
            return null;
        }

        return $this->isSale()
            ? $category->default_sale_account_code
            : $category->default_purchase_account_code;
    }
}
