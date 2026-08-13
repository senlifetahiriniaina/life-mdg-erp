<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $subtotal
 * @property string $total
 */
class InvoiceLine extends Model
{
    use HasFactory;
    protected $table = 'acc_invoice_lines';

    protected $fillable = [
        'invoice_id', 'account_id', 'product_id', 'description',
        'quantity', 'unit_price', 'tax_rate', 'subtotal', 'tax_amount', 'total',
        'match_ref', 'matched_by', 'matched_at',
        'discount_percent', 'tax_percent',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'encrypted:decimal:4',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'encrypted:decimal:2',
        'tax_amount' => 'encrypted:decimal:2',
        'total' => 'encrypted:decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
