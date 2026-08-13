<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Logistics\Database\Factories\LocationFactory;

class Location extends Model
{
    use HasFactory;

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    protected $table = 'logistics_locations';

    protected $fillable = [
        'warehouse_id',
        'parent_id',
        'name',
        'code',
        'type',
        'location_class',
        'capacity_units',
        'occupied_units',
        'max_weight_kg',
        'temperature_class',
        'is_active',
    ];

    protected $casts = [
        'capacity_units' => 'float',
        'occupied_units' => 'float',
        'max_weight_kg' => 'float',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function putawayRules(): HasMany
    {
        return $this->hasMany(PutawayRule::class, 'location_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
