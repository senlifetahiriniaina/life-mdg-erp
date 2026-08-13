<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\UnitFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property string $type
 */
class Unit extends Model
{
    use HasFactory;

    protected $table = 'inventory_units';

    protected $fillable = ['name', 'symbol', 'type'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }
}
