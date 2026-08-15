<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AssetImpairment extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'asset_impairments';

    protected $fillable = [
        'fixed_asset_id',
        'impairment_date',
        'original_cost',
        'accumulated_depreciation_before',
        'book_value_before',
        'fair_value',
        'impairment_loss',
        'new_book_value',
        'impairment_reason',
        'journal_entry_id',
        'status',
    ];

    protected $casts = [
        'impairment_date' => 'date',
        'original_cost' => 'decimal:2',
        'accumulated_depreciation_before' => 'decimal:2',
        'book_value_before' => 'decimal:2',
        'fair_value' => 'decimal:2',
        'impairment_loss' => 'decimal:2',
        'new_book_value' => 'decimal:2',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }
}
