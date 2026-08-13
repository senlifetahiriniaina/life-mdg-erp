<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\AssetDisposalFactory;

/**
 * @property int $id
 * @property int $asset_id
 * @property Carbon|null $disposal_date
 * @property string $disposal_type
 * @property string $disposal_proceeds
 * @property string $net_book_value_at_disposal
 * @property string $gain_loss
 * @property string|null $notes
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FixedAsset $asset
 * @property-read User|null $createdBy
 */
class AssetDisposal extends Model
{
    use HasFactory;

    protected static function newFactory(): AssetDisposalFactory
    {
        return AssetDisposalFactory::new();
    }

    protected $table = 'acc_asset_disposals';

    protected $fillable = [
        'asset_id', 'disposal_date', 'disposal_type', 'disposal_proceeds',
        'net_book_value_at_disposal', 'gain_loss', 'notes', 'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'disposal_proceeds' => 'decimal:2',
        'net_book_value_at_disposal' => 'decimal:2',
        'gain_loss' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGain(): bool
    {
        return (float) $this->gain_loss > 0;
    }
}
