<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\LotFactory;

/**
 * @property int $id
 * @property int $product_id
 * @property string $lot_number
 * @property string|null $serial_number
 * @property Carbon|null $manufacture_date
 * @property Carbon|null $expiry_date
 * @property string $quantity
 * @property string $status
 * @property int|null $warehouse_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Lot extends Model
{
    use HasFactory;

    protected $table = 'inventory_lots';

    protected $fillable = [
        'product_id',
        'lot_number',
        'serial_number',
        'manufacture_date',
        'expiry_date',
        'quantity',
        'status',
        'warehouse_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'quantity' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function newFactory(): LotFactory
    {
        return LotFactory::new();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(LotMovement::class, 'lot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expiry_date !== null && $this->expiry_date->isPast());
    }

    public function isAvailable(): bool
    {
        return $this->isActive() && (float) $this->quantity > 0;
    }

    public function expire(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function quarantine(): void
    {
        $this->update(['status' => 'quarantine']);
    }

    public function receive(float $qty): void
    {
        $this->increment('quantity', $qty);
        $this->refresh();

        $this->movements()->create([
            'movement_type' => 'receipt',
            'quantity' => $qty,
        ]);
    }

    public function issue(float $qty): void
    {
        if ($qty > (float) $this->quantity) {
            throw new \InvalidArgumentException(
                "Cannot issue {$qty} units; only {$this->quantity} available."
            );
        }

        $this->update(['quantity' => max(0, (float) $this->quantity - $qty)]);

        $this->movements()->create([
            'movement_type' => 'issue',
            'quantity' => $qty,
        ]);
    }
}
