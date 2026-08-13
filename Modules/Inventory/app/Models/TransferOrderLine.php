<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\TransferOrderLineFactory;

/**
 * @property int $id
 * @property int $transfer_order_id
 * @property int $product_id
 * @property string $requested_quantity
 * @property string|null $approved_quantity
 * @property string|null $shipped_quantity
 * @property string|null $received_quantity
 * @property string $unit_cost
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TransferOrderLine extends Model
{
    use HasFactory;

    protected static function newFactory(): TransferOrderLineFactory
    {
        return TransferOrderLineFactory::new();
    }

    protected $table = 'inventory_transfer_order_lines';

    protected $fillable = [
        'transfer_order_id', 'product_id', 'requested_quantity', 'approved_quantity',
        'shipped_quantity', 'received_quantity', 'unit_cost', 'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:4',
        'approved_quantity' => 'decimal:4',
        'shipped_quantity' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class, 'transfer_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function lineValue(): float
    {
        $qty = $this->approved_quantity ?? $this->requested_quantity;

        return (float) $qty * (float) $this->unit_cost;
    }

    public function receivedVariance(): float
    {
        return (float) $this->received_quantity - (float) $this->shipped_quantity;
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->shipped_quantity > 0
            && (float) $this->received_quantity >= (float) $this->shipped_quantity;
    }
}
