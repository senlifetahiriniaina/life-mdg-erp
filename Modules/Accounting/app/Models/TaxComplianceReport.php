<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxComplianceReport extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'tax_compliance_reports';

    protected $fillable = [
        'company_id',
        'tax_jurisdiction_id',
        'report_period_start',
        'report_period_end',
        'status',
        'tax_data',
        'compliance_checks',
        'notes',
        'total_tax_liability',
        'total_tax_paid',
        'tax_due_or_refund',
        'filed_at',
        'filing_reference_number',
    ];

    protected $casts = [
        'report_period_start' => 'date',
        'report_period_end' => 'date',
        'tax_data' => 'json',
        'compliance_checks' => 'json',
        'total_tax_liability' => 'decimal:2',
        'total_tax_paid' => 'decimal:2',
        'tax_due_or_refund' => 'decimal:2',
        'filed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(TaxJurisdiction::class, 'tax_jurisdiction_id');
    }
}
