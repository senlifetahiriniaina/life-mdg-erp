<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\ConsolidationMemberFactory;

class ConsolidationMember extends Model
{
    use HasFactory;

    protected $table = 'acc_consolidation_members';

    protected static function newFactory(): ConsolidationMemberFactory
    {
        return ConsolidationMemberFactory::new();
    }

    protected $fillable = [
        'consolidation_group_id',
        'subsidiary_company_id',
        'ownership_percentage',
        'relationship_type',
        'acquisition_date',
        'acquisition_price',
        'exchange_rate',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
        'acquisition_date' => 'date',
        'acquisition_price' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }
}
