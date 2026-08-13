<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\RmaFactory;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $order_id
 * @property string $customer_name
 * @property string $reason
 * @property string $status
 * @property array<int, mixed> $items
 * @property string $return_method
 * @property Carbon|null $approved_at
 * @property Carbon|null $received_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Rma extends Model
{
    use HasFactory;

    protected static function newFactory(): RmaFactory
    {
        return RmaFactory::new();
    }

    protected $table = 'inventory_rmas';

    protected $fillable = [
        'reference',
        'order_id',
        'customer_name',
        'reason',
        'status',
        'items',
        'return_method',
        'approved_at',
        'received_at',
        'refunded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'items' => 'array',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];
}
