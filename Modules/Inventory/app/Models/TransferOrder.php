<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\TransferOrderFactory;

/**
 * @property int $id
 * @property string $reference
 * @property int $from_warehouse_id
 * @property int $to_warehouse_id
 * @property string $status
 * @property string $type
 * @property string|null $priority
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $received_at
 * @property Carbon|null $expected_delivery_date
 * @property string|null $notes
 * @property int $total_items
 * @property string $total_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TransferOrder extends Model
{
    use HasFactory;

    protected static function newFactory(): TransferOrderFactory
    {
        return TransferOrderFactory::new();
    }

    protected $table = 'inventory_transfer_orders';

    protected $fillable = [
        'reference', 'from_warehouse_id', 'to_warehouse_id', 'status', 'type',
        'priority', 'requested_by', 'approved_by', 'approved_at', 'shipped_at',
        'received_at', 'expected_delivery_date', 'notes', 'total_items', 'total_value',
        'company_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'total_value' => 'decimal:2',
        'total_items' => 'integer',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransferOrderLine::class, 'transfer_order_id');
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canBeShipped(): bool
    {
        return $this->status === 'approved';
    }

    public function canBeReceived(): bool
    {
        return $this->status === 'in_transit';
    }

    public function isOverdue(): bool
    {
        return $this->expected_delivery_date !== null
            && $this->received_at === null
            && $this->expected_delivery_date->isPast();
    }

    public function totalQuantityRequested(): float
    {
        return (float) $this->lines->sum('requested_quantity');
    }

    public function totalQuantityReceived(): float
    {
        return (float) $this->lines->sum('received_quantity');
    }
}
