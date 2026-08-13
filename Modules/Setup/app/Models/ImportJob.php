<?php

declare(strict_types=1);

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;

/**
 * @property int         $id
 * @property int         $tenant_id
 * @property string      $name
 * @property string      $source_type
 * @property string|null $source_file_path
 * @property string|null $source_db_driver
 * @property array|null  $source_db_config
 * @property string      $target_module
 * @property string      $target_entity
 * @property string      $status
 * @property int|null    $total_rows
 * @property int         $imported_rows
 * @property int         $failed_rows
 * @property array|null  $error_summary
 * @property bool        $ai_mapping_used
 * @property float|null  $ai_mapping_confidence
 * @property int         $created_by
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ImportJob extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'setup_import_jobs';

    protected $fillable = [
        'tenant_id',
        'name',
        'source_type',
        'source_file_path',
        'source_db_driver',
        'source_db_config',
        'target_module',
        'target_entity',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'error_summary',
        'ai_mapping_used',
        'ai_mapping_confidence',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'source_db_config'      => 'encrypted:array',
        'error_summary'         => 'array',
        'ai_mapping_confidence' => 'float',
        'ai_mapping_used'       => 'boolean',
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return HasMany<FieldMapping> */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(FieldMapping::class, 'import_job_id');
    }

    /** @return HasMany<ImportError> */
    public function importErrors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'import_job_id');
    }

    /** @return HasOne<SourceSchema> */
    public function sourceSchema(): HasOne
    {
        return $this->hasOne(SourceSchema::class, 'import_job_id');
    }

    /** @return BelongsTo<User, self> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /** @param Builder<self> $query */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @param Builder<self> $query */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** @param Builder<self> $query */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('target_module', $module);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Whether the job can still be edited (mappings updated).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['pending', 'mapping'], true);
    }

    /**
     * Whether the job is currently running (no edits allowed).
     */
    public function isRunning(): bool
    {
        return in_array($this->status, ['analyzing', 'importing'], true);
    }

    /**
     * Percentage of rows successfully imported (0–100).
     */
    public function getProgressPercent(): float
    {
        if ($this->total_rows === null || $this->total_rows === 0) {
            return 0.0;
        }

        return round(($this->imported_rows / $this->total_rows) * 100, 2);
    }
}
