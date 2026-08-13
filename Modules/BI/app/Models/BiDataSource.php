<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property array<string, mixed> $connection_config
 * @property string $status
 * @property Carbon|null $last_tested_at
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $creator
 */
class BiDataSource extends Model
{
    use HasFactory;
    protected $table = 'bi_data_sources';

    protected $fillable = [
        'name',
        'type',
        'connection_config',
        'status',
        'last_tested_at',
        'created_by',
    ];

    protected $casts = [
        'connection_config' => 'encrypted:array',
        'last_tested_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
