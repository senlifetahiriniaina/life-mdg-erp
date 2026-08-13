<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\QuoteLineFactory;

/**
 * @property int $id
 * @property int $quote_id
 * @property int|null $product_bundle_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $discount_pct
 * @property string $line_total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Quote $quote
 * @property-read ProductBundle|null $productBundle
 */
class QuoteLine extends Model
{
    use HasFactory;

    protected $table = 'crm_quote_lines';

    protected $fillable = [
        'quote_id',
        'product_bundle_id',
        'description',
        'quantity',
        'unit_price',
        'discount_pct',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function newFactory(): QuoteLineFactory
    {
        return QuoteLineFactory::new();
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function productBundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'product_bundle_id');
    }
}
