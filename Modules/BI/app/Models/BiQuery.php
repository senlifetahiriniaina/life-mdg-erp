<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $created_by
 * @property string $name
 * @property string $sql_query
 * @property string $datasource
 * @property int $result_cache_ttl
 * @property bool $is_public
 * @property Carbon|null $last_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BiQuery extends Model
{
    use HasFactory;
    protected $table = 'bi_queries';

    protected $fillable = [
        'created_by',
        'name',
        'sql_query',
        'datasource',
        'result_cache_ttl',
        'is_public',
        'last_run_at',
    ];

    protected $casts = [
        'result_cache_ttl' => 'integer',
        'is_public' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
