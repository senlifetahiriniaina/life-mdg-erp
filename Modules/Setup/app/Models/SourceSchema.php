<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $import_job_id
 * @property array       $detected_columns
 * @property int|null    $row_count
 * @property array|null  $sheet_names
 * @property string      $detected_encoding
 * @property string|null $detected_delimiter
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class SourceSchema extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'setup_source_schemas';

    protected $fillable = [
        'import_job_id',
        'detected_columns',
        'row_count',
        'sheet_names',
        'detected_encoding',
        'detected_delimiter',
    ];

    protected $casts = [
        'detected_columns' => 'array',
        'sheet_names'      => 'array',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return BelongsTo<ImportJob, self> */
    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
