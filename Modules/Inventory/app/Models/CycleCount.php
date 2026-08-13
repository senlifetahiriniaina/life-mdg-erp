<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\CycleCountFactory;

/**
 * @property int $id
 * @property string $reference
 * @property int $warehouse_id
 * @property string $status
 * @property string $count_date
 * @property int|null $assigned_to
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CycleCount extends Model
{
    use HasFactory;

    protected static function newFactory(): CycleCountFactory
    {
        return CycleCountFactory::new();
    }

    protected $table = 'inventory_cycle_counts';

    protected $fillable = [
        'reference',
        'warehouse_id',
        'status',
        'count_date',
        'assigned_to',
        'notes',
    ];

    protected $casts = [
        'count_date' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CycleCountLine::class);
    }
}
