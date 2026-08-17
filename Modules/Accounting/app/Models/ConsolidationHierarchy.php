<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ConsolidationHierarchy extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'consolidation_hierarchies';

    protected $fillable = [
        'name',
        'description',
        'type',
        'parent_company_id',
        'company_id',
        'ownership_percentage',
        'effective_date',
        'end_date',
        'is_active',
        'metadata',
        'status',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'json',
    ];

    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'parent_company_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'company_id');
    }

    public function eliminations(): HasMany
    {
        return $this->hasMany(ConsolidationElimination::class, 'consolidation_hierarchy_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ConsolidationPeriod::class, 'consolidation_hierarchy_id');
    }
}
