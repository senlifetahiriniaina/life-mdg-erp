<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int $tenant_id
 * @property int $report_definition_id
 * @property string $name
 * @property string $frequency
 * @property string|null $cron_expression
 * @property array<string> $recipients
 * @property \Carbon\Carbon|null $last_run_at
 * @property \Carbon\Carbon|null $next_run_at
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ReportSchedule extends Model
{
    use HasFactory;
    use AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'report_schedules';

    protected $fillable = [
        'tenant_id',
        'report_definition_id',
        'name',
        'frequency',
        'cron_expression',
        'recipients',
        'last_run_at',
        'next_run_at',
        'is_active',
    ];

    protected $casts = [
        'recipients'  => 'array',
        'is_active'   => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            });
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Compute the next run timestamp based on frequency.
     */
    public function computeNextRunAt(): \Carbon\Carbon
    {
        $from = $this->last_run_at ?? now();

        return match ($this->frequency) {
            'daily'   => $from->copy()->addDay(),
            'weekly'  => $from->copy()->addWeek(),
            'monthly' => $from->copy()->addMonth(),
            default   => $from->copy()->addDay(), // fallback for 'custom'
        };
    }
}
