<?php

namespace Modules\Achats\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $cost_center_id
 * @property string $budget_type
 * @property string $allocated_amount
 * @property string $spent_amount
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PoBudgetAllocation extends Model
{
    use HasFactory;
    protected $table = 'achats_po_budget_allocations';

    protected $fillable = [
        'purchase_order_id',
        'cost_center_id',
        'budget_type',
        'allocated_amount',
        'spent_amount',
        'status',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:4',
        'spent_amount' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function checkBudgetAvailable(float $amount): bool
    {
        $available = (float) $this->allocated_amount - (float) $this->spent_amount;

        return $available >= $amount;
    }

    public function allocate(float $amount): void
    {
        $this->update([
            'spent_amount' => (float) $this->spent_amount + $amount,
        ]);
    }

    public function release(): void
    {
        $this->update([
            'status' => 'released',
            'spent_amount' => 0,
        ]);
    }
}
