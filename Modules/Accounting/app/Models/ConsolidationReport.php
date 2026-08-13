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
 * @property int $parent_company_id
 * @property Carbon $report_date
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $report_type
 * @property string $status
 * @property array<int, mixed>|null $included_companies
 * @property string $total_revenue
 * @property string $total_expenses
 * @property string $net_income
 * @property string $total_assets
 * @property string $total_liabilities
 * @property string $minority_interest
 * @property string $eliminations_total
 * @property array<string, mixed>|null $report_data
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $parentCompany
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
        'parent_company_id',
        'report_date',
        'period_start',
        'period_end',
        'report_type',
        'status',
        'included_companies',
        'total_revenue',
        'total_expenses',
        'net_income',
        'total_assets',
        'total_liabilities',
        'minority_interest',
        'eliminations_total',
        'report_data',
        'generated_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_revenue' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'net_income' => 'decimal:2',
        'total_assets' => 'decimal:2',
        'total_liabilities' => 'decimal:2',
        'minority_interest' => 'decimal:2',
        'eliminations_total' => 'decimal:2',
        'included_companies' => 'json',
        'report_data' => 'json',
        'generated_at' => 'datetime',
    ];

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    public function netIncomeMargin(): float
    {
        if ((float) $this->total_revenue == 0) {
            return 0.0;
        }

        return (float) $this->net_income / (float) $this->total_revenue * 100;
    }

    public function debtToAssetRatio(): float
    {
        if ((float) $this->total_assets == 0) {
            return 0.0;
        }

        return (float) $this->total_liabilities / (float) $this->total_assets;
    }

    public function consolidatedEquity(): float
    {
        return (float) $this->total_assets - (float) $this->total_liabilities - (float) $this->minority_interest;
    }
}
