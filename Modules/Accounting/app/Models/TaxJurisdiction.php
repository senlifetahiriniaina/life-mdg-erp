<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxJurisdiction extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'tax_jurisdictions';

    protected $fillable = [
        'jurisdiction_code',
        'jurisdiction_name',
        'country_code',
        'region_code',
        'tax_type',
        'tax_rate',
        'effective_from',
        'effective_to',
        'tax_calculation_method',
        'tax_rules',
        'exemptions',
        'filing_requirements',
        'filing_due_date',
        'is_active',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'filing_due_date' => 'date',
        'is_active' => 'boolean',
        'tax_rules' => 'json',
        'exemptions' => 'json',
        'filing_requirements' => 'json',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(TaxComplianceRule::class, 'tax_jurisdiction_id');
    }

    public function filingTemplates(): HasMany
    {
        return $this->hasMany(TaxFilingTemplate::class, 'tax_jurisdiction_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TaxComplianceReport::class, 'tax_jurisdiction_id');
    }
}
