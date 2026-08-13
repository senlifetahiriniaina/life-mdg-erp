<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $filename
 * @property string $file_path
 * @property string $file_type
 * @property string $target_entity
 * @property string $status
 * @property array<string,mixed>|null $column_mapping
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $failed_rows
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImportJob extends Model
{
    use HasFactory;
    protected $table = 'core_import_jobs';

    protected $fillable = [
        'user_id',
        'filename',
        'file_path',
        'file_type',
        'target_entity',
        'status',
        'column_mapping',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'error_message',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'import_job_id');
    }
}
