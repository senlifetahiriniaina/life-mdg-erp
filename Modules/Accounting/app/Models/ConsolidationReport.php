<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\ConsolidationReportFactory;

/**
 * @property int $id
 * @property int $consolidation_group_id
 * @property string $report_type
 * @property string $reporting_currency
 * @property array<string, mixed>|null $consolidated_data
 * @property array<string, mixed>|null $intercompany_eliminations
 * @property array<string, mixed>|null $exchange_differences
 * @property string $total_adjustments
 * @property string $status
 * @property string|null $auditor_notes
 * @property int|null $created_by
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConsolidationGroup $group
 */
class ConsolidationReport extends Model
{
    use HasFactory;

    protected $table = 'acc_consolidation_reports';

    protected static function newFactory(): ConsolidationReportFactory
    {
        return ConsolidationReportFactory::new();
    }

    protected $fillable = [
        'consolidation_group_id',
        'report_type',
        'reporting_currency',
        'consolidated_data',
        'intercompany_eliminations',
        'exchange_differences',
        'total_adjustments',
        'status',
        'auditor_notes',
        'created_by',
        'finalized_at',
    ];

    protected $casts = [
        'total_adjustments' => 'decimal:4',
        'consolidated_data' => 'json',
        'intercompany_eliminations' => 'json',
        'exchange_differences' => 'json',
        'finalized_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }
}
