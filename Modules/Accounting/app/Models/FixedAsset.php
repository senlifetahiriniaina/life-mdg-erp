<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\FixedAssetFactory;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $asset_code
 * @property string $name
 * @property string|null $description
 * @property string $asset_class
 * @property Carbon|null $acquisition_date
 * @property string $acquisition_cost
 * @property string $salvage_value
 * @property int $useful_life_years
 * @property string $depreciation_method
 * @property int $asset_account_id
 * @property int $depreciation_expense_account_id
 * @property int $accumulated_depreciation_account_id
 * @property string $status
 * @property Carbon|null $disposal_date
 * @property string|null $disposal_proceeds
 * @property string|null $disposal_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AssetDepreciation> $depreciations
 * @property-read AssetDisposal|null $disposal
 */
class FixedAsset extends Model
{
    use HasFactory;

    protected static function newFactory(): FixedAssetFactory
    {
        return FixedAssetFactory::new();
    }

    protected $table = 'acc_fixed_assets';

    protected $fillable = [
        'tenant_id', 'company_id', 'asset_code', 'name', 'description', 'asset_class',
        'acquisition_date', 'acquisition_cost', 'salvage_value', 'useful_life_years',
        'depreciation_method', 'asset_account_id', 'depreciation_expense_account_id',
        'accumulated_depreciation_account_id', 'status', 'disposal_date',
        'disposal_proceeds', 'disposal_notes',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost' => 'encrypted:decimal:2',
        'salvage_value' => 'encrypted:decimal:2',
        'disposal_proceeds' => 'encrypted:decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class, 'asset_id');
    }

    public function disposal(): HasOne
    {
        return $this->hasOne(AssetDisposal::class, 'asset_id');
    }

    public function depreciableAmount(): float
    {
        return (float) $this->acquisition_cost - (float) $this->salvage_value;
    }

    public function getAccumulatedDepreciationAttribute(): float
    {
        return (float) $this->depreciations()->sum('depreciation_amount');
    }

    public function getNetBookValueAttribute(): float
    {
        return (float) $this->acquisition_cost - $this->getAccumulatedDepreciationAttribute();
    }

    public function isFullyDepreciated(): bool
    {
        $accumulated = $this->getAccumulatedDepreciationAttribute();

        return $accumulated >= $this->depreciableAmount();
    }

    public function remainingUsefulLifeMonths(): int
    {
        $totalMonths = $this->useful_life_years;
        $monthsElapsed = (int) $this->acquisition_date->diffInMonths(now());

        return max(0, $totalMonths - $monthsElapsed);
    }
}
