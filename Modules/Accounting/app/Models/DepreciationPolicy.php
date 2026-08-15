<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationPolicy extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'depreciation_policies';

    protected $fillable = [
        'company_id',
        'policy_name',
        'asset_category',
        'depreciation_method',
        'default_useful_life_years',
        'default_residual_percentage',
        'tax_depreciation_method',
        'tax_useful_life_years',
        'policy_description',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'default_useful_life_years' => 'integer',
        'default_residual_percentage' => 'decimal:2',
        'tax_useful_life_years' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'company_id');
    }
}
