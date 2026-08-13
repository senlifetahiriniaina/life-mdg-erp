<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\RedistributionRuleFactory;

/**
 * @property int $id
 * @property string $name
 * @property int $from_warehouse_id
 * @property int $to_warehouse_id
 * @property int|null $product_id
 * @property string $rule_type
 * @property string $trigger_threshold
 * @property string $transfer_quantity
 * @property bool $is_active
 * @property int $priority
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RedistributionRule extends Model
{
    use HasFactory;

    protected static function newFactory(): RedistributionRuleFactory
    {
        return RedistributionRuleFactory::new();
    }

    protected $table = 'inventory_redistribution_rules';

    protected $fillable = [
        'name', 'from_warehouse_id', 'to_warehouse_id', 'product_id',
        'rule_type', 'trigger_threshold', 'transfer_quantity', 'is_active', 'priority',
    ];

    protected $casts = [
        'trigger_threshold' => 'decimal:4',
        'transfer_quantity' => 'decimal:4',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function shouldTrigger(float $currentStock): bool
    {
        return $currentStock <= (float) $this->trigger_threshold;
    }
}
