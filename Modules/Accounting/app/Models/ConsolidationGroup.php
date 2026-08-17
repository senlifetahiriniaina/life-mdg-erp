<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\ConsolidationGroupFactory;

/**
 * A group of companies consolidated together for reporting purposes
 * (parent + subsidiaries/associates), independent of the strict acc_companies
 * parent/subsidiary tree so it can span entities that aren't recorded there.
 */
class ConsolidationGroup extends Model
{
    use HasFactory;

    protected $table = 'acc_consolidation_groups';

    protected static function newFactory(): ConsolidationGroupFactory
    {
        return ConsolidationGroupFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'currency',
        'is_active',
        'consolidation_method',
        'parent_company_id',
        'fiscal_year',
        'consolidation_date',
        'created_by',
        'status',
        'auto_eliminate_intercompany',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_eliminate_intercompany' => 'boolean',
        'consolidation_date' => 'date',
        'fiscal_year' => 'integer',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ConsolidationMember::class);
    }

    public function intercompanyTransactions(): HasMany
    {
        return $this->hasMany(IntercompanyTransaction::class, 'consolidation_group_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ConsolidationGroupEntry::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ConsolidationReport::class);
    }

    public function getTotalOwnershipPercentage(): float
    {
        return (float) $this->members()->sum('ownership_percentage');
    }
}
