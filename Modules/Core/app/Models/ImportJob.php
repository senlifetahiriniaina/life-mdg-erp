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
 * Chantier 32.1: $fillable used to declare processed_rows/failed_rows/
 * error_message — none of which have ever existed as real columns on
 * core_import_jobs (confirmed via Schema::getColumnListing()); the real
 * columns are processed/failed/errors. Every write through
 * ImportExecutorService::executeImport()'s $job->increment('processed_rows')
 * (hit on every row processed) and ::rollbackImport()'s
 * ['processed_rows' => 0] update was a guaranteed "no such column"
 * QueryException on every real import — this pipeline had never actually
 * completed an import successfully. Fixed by remapping onto the real
 * columns rather than adding duplicate ones. ai_suggestions is a new real
 * column (see the migration alongside this fix) backing AiMappingService's
 * previously-orphaned suggestMapping() output, now wired into
 * ExtractAndMapImportJob.
 *
 * @property int $id
 * @property int $user_id
 * @property string $filename
 * @property string $file_path
 * @property string $file_type
 * @property string $target_entity
 * @property string $status
 * @property array<string,mixed>|null $column_mapping
 * @property array<string,mixed>|null $ai_suggestions
 * @property int $total_rows
 * @property int $processed
 * @property int $failed
 * @property string|null $errors
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
        'ai_suggestions',
        'total_rows',
        'processed',
        'failed',
        'errors',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'ai_suggestions' => 'array',
        'total_rows' => 'integer',
        'processed' => 'integer',
        'failed' => 'integer',
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
