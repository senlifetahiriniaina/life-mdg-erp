<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Database\Factories\PutawayRuleFactory;

class PutawayRule extends Model
{
    use HasFactory;

    protected static function newFactory(): PutawayRuleFactory
    {
        return PutawayRuleFactory::new();
    }

    protected $table = 'logistics_putaway_rules';

    protected $fillable = [
        'name',
        'priority',
        'product_id',
        'product_category_id',
        'location_id',
        'strategy',
        'temperature_required',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
