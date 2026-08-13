<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\PickingWaveFactory;

/**
 * @property int $id
 * @property string $status
 * @property int|null $picker_id
 * @property array<int, mixed> $order_ids
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $picker
 * @property-read Collection<int, PickLine> $lines
 */
class PickingWave extends Model
{
    use HasFactory;

    protected static function newFactory(): PickingWaveFactory
    {
        return PickingWaveFactory::new();
    }

    protected $table = 'inventory_picking_waves';

    protected $fillable = [
        'status',
        'picker_id',
        'order_ids',
        'started_at',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'order_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, self> */
    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_id');
    }

    /** @return HasMany<PickLine, self> */
    public function lines(): HasMany
    {
        return $this->hasMany(PickLine::class, 'wave_id');
    }
}
