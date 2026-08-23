<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\DemandForecastFactory;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $warehouse_id
 * @property string $period_type
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property float $forecasted_qty
 * @property float|null $actual_qty
 * @property float|null $confidence
 * @property string $method
 * @property array<string,mixed>|null $metadata
 * @property string $status
 * @property-read Product   $product
 * @property-read Warehouse|null $warehouse
 */
class DemandForecast extends Model
{
    use HasFactory;

    protected $table = 'inventory_demand_forecasts';

    protected static function newFactory(): DemandForecastFactory
    {
        return DemandForecastFactory::new();
    }

    protected $fillable = [
        'product_id', 'warehouse_id', 'period_type', 'period_start', 'period_end',
        'forecasted_qty', 'actual_qty', 'confidence', 'method', 'metadata', 'status',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'forecasted_qty' => 'float',
            'actual_qty' => 'float',
            'confidence' => 'float',
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function accuracy(): ?float
    {
        if ($this->actual_qty === null || $this->forecasted_qty == 0) {
            return null;
        }

        return round(100 - abs(($this->actual_qty - $this->forecasted_qty) / $this->forecasted_qty) * 100, 2);
    }
}
