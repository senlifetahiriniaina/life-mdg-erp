<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\ConsolidationGroupEntryFactory;

/**
 * Elimination/adjustment entries posted against a ConsolidationGroup.
 * Distinct from the legacy ConsolidationEntry model, which belongs to the
 * separate ConsolidationHierarchy/ConsolidationPeriod stack (consolidation_entries
 * table, no acc_ prefix) and is unrelated to this one.
 */
class ConsolidationGroupEntry extends Model
{
    use HasFactory;

    protected $table = 'acc_consolidation_entries';

    protected static function newFactory(): ConsolidationGroupEntryFactory
    {
        return ConsolidationGroupEntryFactory::new();
    }

    protected $fillable = [
        'consolidation_group_id',
        'entry_type',
        'related_transaction_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }
}
