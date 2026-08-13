<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reporting\Models\ReportShare;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $module
 * @property string|null $description
 * @property string $query_template
 * @property array<string,mixed>|null $parameters_schema
 * @property string $output_format
 * @property string $report_type  table|chart|pivot|export
 * @property bool $is_system
 * @property bool $is_active
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ReportDefinition extends Model
{
    use AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'report_definitions';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'module',
        'description',
        'query_template',
        'parameters_schema',
        'output_format',
        'report_type',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'parameters_schema' => 'array',
        'is_system'         => 'boolean',
        'is_active'         => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function executions(): HasMany
    {
        return $this->hasMany(ReportExecution::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ReportShare::class, 'report_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Reports visible to a given tenant: tenant-specific OR global (null tenant_id).
     */
    public function scopeVisibleTo(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
              ->orWhereNull('tenant_id');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Reports belonging exclusively to the given tenant (excludes global reports).
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForReportType(Builder $query, string $reportType): Builder
    {
        return $query->where('report_type', $reportType);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isExportable(): bool
    {
        return in_array($this->output_format, ['pdf', 'excel'], true);
    }
}
