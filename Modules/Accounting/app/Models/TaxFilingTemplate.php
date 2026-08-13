<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxFilingTemplate extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'tax_filing_templates';

    protected $fillable = [
        'tax_jurisdiction_id',
        'filing_form_number',
        'filing_type',
        'field_mappings',
        'calculation_rules',
        'validation_rules',
        'filing_instructions',
        'last_updated',
    ];

    protected $casts = [
        'field_mappings' => 'json',
        'calculation_rules' => 'json',
        'validation_rules' => 'json',
        'last_updated' => 'date',
    ];

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(TaxJurisdiction::class, 'tax_jurisdiction_id');
    }
}
