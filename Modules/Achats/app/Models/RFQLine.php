<?php

namespace Modules\Achats\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Achats\Database\Factories\RFQLineFactory;
use Modules\Inventory\Models\Product;

/**
 * @property int $id
 * @property int $rfq_id
 * @property int|null $product_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property Carbon $required_date
 * @property int|null $preferred_supplier_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RFQLine extends Model
{
    use HasFactory;
    protected $table = 'achats_rfq_lines';

    protected static function newFactory(): RFQLineFactory
    {
        return RFQLineFactory::new();
    }

    protected $fillable = [
        'rfq_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'required_date',
        'preferred_supplier_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'required_date' => 'date',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class, 'rfq_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }
}
