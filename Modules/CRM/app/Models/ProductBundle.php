<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Database\Factories\ProductBundleFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $base_price
 * @property string $discount_pct
 * @property array<int,array<string,mixed>> $items
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, QuoteLine> $quoteLines
 */
class ProductBundle extends Model
{
    use HasFactory;

    protected $table = 'crm_product_bundles';

    protected $fillable = [
        'name',
        'description',
        'base_price',
        'discount_pct',
        'items',
        'active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'items' => 'array',
        'active' => 'boolean',
    ];

    protected static function newFactory(): ProductBundleFactory
    {
        return ProductBundleFactory::new();
    }

    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class, 'product_bundle_id');
    }
}
