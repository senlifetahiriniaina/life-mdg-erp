<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaxComplianceRule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'tax_compliance_rules';

    protected $fillable = [
        'tax_jurisdiction_id',
        'rule_name',
        'rule_type',
        'rule_conditions',
        'rule_actions',
        'description',
        'requires_documentation',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'rule_conditions' => 'json',
        'rule_actions' => 'json',
        'requires_documentation' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(TaxJurisdiction::class, 'tax_jurisdiction_id');
    }
}
