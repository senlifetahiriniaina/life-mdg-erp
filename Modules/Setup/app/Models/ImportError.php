<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int         $id
 * @property int         $import_job_id
 * @property int         $row_number
 * @property array|null  $source_data
 * @property string|null $field
 * @property string      $error_type
 * @property string      $error_message
 * @property bool        $is_skipped
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ImportError extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'setup_import_errors';

    protected $fillable = [
        'import_job_id',
        'row_number',
        'source_data',
        'field',
        'error_type',
        'error_message',
        'is_skipped',
    ];

    protected $casts = [
        'source_data' => 'array',
        'is_skipped'  => 'boolean',
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
