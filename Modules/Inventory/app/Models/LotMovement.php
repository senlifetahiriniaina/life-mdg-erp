<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\LotMovementFactory;

/**
 * @property int $id
 * @property int $lot_id
 * @property string $movement_type
 * @property string $quantity
 * @property string|null $reference
 * @property int|null $warehouse_from_id
 * @property int|null $warehouse_to_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LotMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_lot_movements';

    protected $fillable = [
        'lot_id',
        'movement_type',
        'quantity',
        'reference',
        'warehouse_from_id',
        'warehouse_to_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    protected static function newFactory(): LotMovementFactory
    {
        return LotMovementFactory::new();
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }

    public function isReceipt(): bool
    {
        return $this->movement_type === 'receipt';
    }

    public function isIssue(): bool
    {
        return $this->movement_type === 'issue';
    }
}
