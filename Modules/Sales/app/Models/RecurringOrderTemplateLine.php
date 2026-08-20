<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOrderTemplateLine extends Model
{
    use HasFactory;

    protected $table = 'sales_recurring_order_template_lines';

    protected $fillable = [
        'recurring_order_template_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'tax_rate',
        'sequence',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'sequence' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurringOrderTemplate::class, 'recurring_order_template_id');
    }
}
