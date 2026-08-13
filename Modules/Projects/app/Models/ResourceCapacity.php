<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Database\Factories\ResourceCapacityFactory;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property string $available_hours
 * @property bool $is_holiday
 * @property bool $is_leave
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ResourceCapacity extends Model
{
    use HasFactory;

    protected static function newFactory(): ResourceCapacityFactory
    {
        return ResourceCapacityFactory::new();
    }

    protected $table = 'prj_resource_capacity';

    protected $fillable = [
        'user_id',
        'date',
        'available_hours',
        'is_holiday',
        'is_leave',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'available_hours' => 'decimal:2',
        'is_holiday' => 'boolean',
        'is_leave' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Whether the user is available on this day.
     */
    public function isAvailable(): bool
    {
        return ! $this->is_holiday && ! $this->is_leave && (float) $this->available_hours > 0;
    }

    /**
     * Effective working hours (0 when holiday or on leave).
     */
    public function effectiveHours(): float
    {
        if ($this->is_holiday || $this->is_leave) {
            return 0.0;
        }

        return (float) $this->available_hours;
    }
}
