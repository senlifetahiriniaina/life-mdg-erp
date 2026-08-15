<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int $sales_order_id
 * @property int|null $product_id
 * @property string $description
 * @property float $quantity
 * @property float $unit_price
 * @property float $discount_percent
 * @property float $tax_rate
 * @property float $line_total
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class SalesOrderLine extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'sales_order_lines';

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'tax_rate',
        'line_total',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'tax_rate'         => 'decimal:2',
        'line_total'       => 'decimal:2',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Compute the line total from quantity, unit price, discount and tax.
     */
    public function computeTotal(): float
    {
        $base       = (float) $this->quantity * (float) $this->unit_price;
        $afterDiscount = $base * (1 - (float) $this->discount_percent / 100);
        $withTax    = $afterDiscount * (1 + (float) $this->tax_rate / 100);

        return round($withTax, 2);
    }
}
