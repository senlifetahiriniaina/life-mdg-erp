<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int         $id
 * @property int         $import_job_id
 * @property string      $source_field
 * @property array|null  $source_sample
 * @property string      $target_field
 * @property string      $target_table
 * @property string      $transform_type
 * @property array|null  $transform_config
 * @property bool        $is_required
 * @property bool        $is_ai_suggested
 * @property float|null  $ai_confidence
 * @property bool        $is_confirmed
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class FieldMapping extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'setup_field_mappings';

    protected $fillable = [
        'import_job_id',
        'source_field',
        'source_sample',
        'target_field',
        'target_table',
        'transform_type',
        'transform_config',
        'is_required',
        'is_ai_suggested',
        'ai_confidence',
        'is_confirmed',
    ];

    protected $casts = [
        'source_sample'    => 'array',
        'transform_config' => 'array',
        'is_required'      => 'boolean',
        'is_ai_suggested'  => 'boolean',
        'ai_confidence'    => 'float',
        'is_confirmed'     => 'boolean',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return BelongsTo<ImportJob, self> */
    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Whether this mapping is confirmed and ready to be used during import.
     */
    public function isReadyToImport(): bool
    {
        return $this->is_confirmed === true;
    }
}
