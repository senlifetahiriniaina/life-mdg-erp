<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $import_job_id
 * @property int $row_index
 * @property array<string,mixed> $raw_data
 * @property array<string,mixed>|null $mapped_data
 * @property string $status
 * @property int|null $created_record_id
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImportRow extends Model
{
    use HasFactory;
    protected $table = 'core_import_rows';

    protected $fillable = [
        'import_job_id',
        'row_index',
        'raw_data',
        'mapped_data',
        'status',
        'created_record_id',
        'error_message',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'mapped_data' => 'array',
        'row_index' => 'integer',
    ];

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
