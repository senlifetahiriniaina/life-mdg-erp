<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\CrossdockOperationFactory;

/**
 * @property int $id
 * @property int|null $inbound_shipment_id
 * @property int|null $outbound_order_id
 * @property int $product_id
 * @property string $qty
 * @property string $status
 * @property Carbon|null $executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
class CrossdockOperation extends Model
{
    use HasFactory;

    protected static function newFactory(): CrossdockOperationFactory
    {
        return CrossdockOperationFactory::new();
    }

    protected $table = 'inventory_crossdock_operations';

    protected $fillable = [
        'inbound_shipment_id',
        'outbound_order_id',
        'product_id',
        'qty',
        'status',
        'executed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'qty' => 'decimal:2',
        'executed_at' => 'datetime',
    ];

    /** @return BelongsTo<Product, self> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
