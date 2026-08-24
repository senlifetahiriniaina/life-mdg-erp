<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\SeasonalFactorFactory;

/**
 * @property int $id
 * @property int|null $product_id
 * @property int|null $category_id
 * @property string $period_type
 * @property int $period_index
 * @property float $factor
 * @property string|null $notes
 * @property-read Product|null  $product
 * @property-read Category|null $category
 */
class SeasonalFactor extends Model
{
    use HasFactory;

    protected $table = 'inventory_seasonal_factors';

    protected static function newFactory(): SeasonalFactorFactory
    {
        return SeasonalFactorFactory::new();
    }

    protected $fillable = [
        'product_id', 'category_id', 'period_type', 'period_index', 'factor', 'notes',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'float',
            'period_index' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
