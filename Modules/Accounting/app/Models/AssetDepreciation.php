<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\AssetDepreciationFactory;

/**
 * @property int $id
 * @property int $asset_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property int $period_month
 * @property int $period_year
 * @property string $depreciation_amount
 * @property string $accumulated_depreciation
 * @property string $net_book_value
 * @property string|null $units_used
 * @property int|null $journal_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FixedAsset $asset
 */
class AssetDepreciation extends Model
{
    use HasFactory;

    protected static function newFactory(): AssetDepreciationFactory
    {
        return AssetDepreciationFactory::new();
    }

    protected $table = 'acc_asset_depreciation';

    protected $fillable = [
        'asset_id', 'period_start', 'period_end', 'period_month', 'period_year',
        'depreciation_amount', 'accumulated_depreciation', 'net_book_value',
        'units_used', 'journal_entry_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'depreciation_amount' => 'encrypted:decimal:2',
        'accumulated_depreciation' => 'encrypted:decimal:2',
        'net_book_value' => 'encrypted:decimal:2',
        'units_used' => 'encrypted:decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
