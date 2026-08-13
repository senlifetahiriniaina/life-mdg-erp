<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\LocationFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $warehouse_id
 */
class Location extends Model
{
    use HasFactory;

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    protected $table = 'inventory_locations';

    protected $fillable = ['warehouse_id', 'name', 'code'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
