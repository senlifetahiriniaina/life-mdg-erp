<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_delivery_routes';

    protected $fillable = [
        'company_id',
        'reference',
        'name',
        'date',
        'status',
        'vehicle_id',
        'driver_id',
        'total_distance_km',
        'total_duration_min',
        'total_stops',
        'optimized',
    ];

    protected $casts = [
        'date'               => 'date',
        'total_distance_km'  => 'decimal:2',
        'total_stops'        => 'integer',
        'total_duration_min' => 'integer',
        'optimized'          => 'boolean',
    ];

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('sequence');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed'   => 'green',
            'in_progress' => 'blue',
            'planned'     => 'gray',
            'cancelled'   => 'red',
            default       => 'gray',
        };
    }

    public function getCompletionRateAttribute(): float
    {
        $total     = $this->stops()->count();
        $completed = $this->stops()->where('status', 'completed')->count();

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
