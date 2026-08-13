<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Logistics\Database\Factories\DeliveryRoundFactory;

class DeliveryRound extends Model
{
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected static string $auditModule = 'Logistics';

    protected static function newFactory(): DeliveryRoundFactory
    {
        return DeliveryRoundFactory::new();
    }

    protected $table = 'logistics_delivery_rounds';

    protected $fillable = [
        'reference',
        'driver_name',
        'driver_phone',
        'vehicle_plate',
        'vehicle_type',
        'carrier_id',
        'status',
        'planned_date',
        'started_at',
        'completed_at',
        'total_stops',
        'total_distance_km',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_distance_km' => 'decimal:2',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class);
    }
}
