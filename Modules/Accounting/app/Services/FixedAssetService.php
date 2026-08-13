<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Accounting\Models\AssetDepreciation;
use Modules\Accounting\Models\AssetDisposal;
use Modules\Accounting\Models\FixedAsset;

class FixedAssetService
{
    /**
     * Create a new fixed asset.
     */
    public function create(array $data): FixedAsset
    {
        $data['asset_code'] ??= $this->generateAssetCode();
        $data['status'] ??= 'active';

        return FixedAsset::create($data);
    }

    /**
     * Calculate and record one month of depreciation for an asset.
     * Returns null if the asset is already fully depreciated.
     */
    public function depreciate(FixedAsset $asset, Carbon $periodDate): ?AssetDepreciation
    {
        if ($asset->isFullyDepreciated() || $asset->status !== 'active') {
            return null;
        }

        $alreadyRecorded = $asset->depreciations()
            ->where('period_year', $periodDate->year)
            ->where('period_month', $periodDate->month)
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        $accumulated = (float) $asset->depreciations()->sum('depreciation_amount');
        $amount = $this->calculatePeriodDepreciation($asset, $periodDate, $accumulated);

        // Cap at remaining depreciable amount
        $remaining = (float) $asset->depreciableAmount() - $accumulated;
        $amount = min($amount, max(0, $remaining));

        if ($amount <= 0) {
            $asset->update(['status' => 'fully-depreciated']);

            return null;
        }

        $newAccumulated = $accumulated + $amount;
        $nbv = (float) $asset->acquisition_cost - $newAccumulated;

        $depreciation = AssetDepreciation::create([
            'asset_id' => $asset->id,
            'period_start' => $periodDate->copy()->startOfMonth(),
            'period_end' => $periodDate->copy()->endOfMonth(),
            'period_month' => $periodDate->month,
            'period_year' => $periodDate->year,
            'depreciation_amount' => round($amount, 2),
            'accumulated_depreciation' => round($newAccumulated, 2),
            'net_book_value' => round(max(0, $nbv), 2),
        ]);

        if ($asset->isFullyDepreciated()) {
            $asset->update(['status' => 'fully-depreciated']);
        }

        return $depreciation;
    }

    /**
     * Depreciate all active assets for a given period (batch job).
     */
    public function depreciateAll(Carbon $periodDate): array
    {
        $results = ['processed' => 0, 'skipped' => 0, 'errors' => []];

        FixedAsset::where('status', 'active')
            ->where('acquisition_date', '<=', $periodDate->copy()->endOfMonth())
            ->each(function (FixedAsset $asset) use ($periodDate, &$results) {
                try {
                    $entry = $this->depreciate($asset, $periodDate);
                    $entry ? $results['processed']++ : $results['skipped']++;
                } catch (\Throwable $e) {
                    $results['errors'][] = ['asset_id' => $asset->id, 'error' => $e->getMessage()];
                }
            });

        return $results;
    }

    /**
     * Generate a full depreciation schedule (all future periods until fully depreciated).
     */
    public function depreciationSchedule(FixedAsset $asset): array
    {
        $schedule = [];
        $period = Carbon::parse($asset->acquisition_date)->startOfMonth()->addMonth();
        $accumulated = (float) $asset->depreciations()->sum('depreciation_amount');
        $nbv = (float) $asset->acquisition_cost - $accumulated;
        $depreciableTotal = (float) $asset->depreciableAmount();
        $iteration = 0;
        $maxIterations = $asset->useful_life_years + 12;

        while ($nbv > (float) $asset->salvage_value && $accumulated < $depreciableTotal && $iteration < $maxIterations) {
            $amount = $this->calculatePeriodDepreciation($asset, $period, $accumulated);
            $remaining = $depreciableTotal - $accumulated;
            $amount = min($amount, max(0, $remaining));
            $accumulated += $amount;
            $nbv = max((float) $asset->salvage_value, (float) $asset->acquisition_cost - $accumulated);

            $schedule[] = [
                'period' => $period->format('Y-m'),
                'period_start' => $period->copy()->startOfMonth()->toDateString(),
                'period_end' => $period->copy()->endOfMonth()->toDateString(),
                'depreciation_amount' => round($amount, 2),
                'accumulated_depreciation' => round($accumulated, 2),
                'net_book_value' => round($nbv, 2),
            ];

            if ($amount <= 0 || $nbv <= (float) $asset->salvage_value) {
                break;
            }

            $period->addMonth();
            $iteration++;
        }

        return $schedule;
    }

    /**
     * Dispose (retire) a fixed asset and record gain/loss.
     */
    public function dispose(
        FixedAsset $asset,
        string $disposalType,
        float $proceeds,
        Carbon $disposalDate,
        ?string $notes = null
    ): AssetDisposal {
        $accumulated = (float) $asset->depreciations()->sum('depreciation_amount');
        $nbvAtDisposal = (float) $asset->acquisition_cost - $accumulated;
        $gainLoss = $proceeds - $nbvAtDisposal;

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposal_date' => $disposalDate,
            'disposal_type' => $disposalType,
            'disposal_proceeds' => $proceeds,
            'net_book_value_at_disposal' => $nbvAtDisposal,
            'gain_loss' => round($gainLoss, 2),
            'notes' => $notes,
        ]);

        $asset->update(['status' => 'disposed', 'disposal_date' => $disposalDate, 'disposal_proceeds' => $proceeds]);

        return $disposal;
    }

    /**
     * Asset register (summary of all assets with current values).
     */
    public function register(array $filters = []): Collection
    {
        $query = FixedAsset::with(['depreciations' => fn ($q) => $q->latest('period_end')->limit(1)])
            ->withCount('depreciations');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['asset_class'])) {
            $query->where('asset_class', $filters['asset_class']);
        }

        return $query->get()->map(function (FixedAsset $a) {
            $accumulated = (float) $a->depreciations()->sum('depreciation_amount');
            $nbv = (float) $a->acquisition_cost - $accumulated;

            return [
                'id' => $a->id,
                'asset_code' => $a->asset_code,
                'name' => $a->name,
                'asset_class' => $a->asset_class,
                'acquisition_date' => $a->acquisition_date->toDateString(),
                'acquisition_cost' => (float) $a->acquisition_cost,
                'accumulated_depreciation' => round($accumulated, 2),
                'net_book_value' => round($nbv, 2),
                'salvage_value' => (float) $a->salvage_value,
                'useful_life_years' => $a->useful_life_years,
                'depreciation_method' => $a->depreciation_method,
                'status' => $a->status,
                'periods_recorded' => $a->depreciations_count,
                'remaining_life_months' => $a->remainingUsefulLifeMonths(),
                'is_fully_depreciated' => $a->isFullyDepreciated(),
            ];
        });
    }

    // ─── Calculation engines ──────────────────────────────────────────────────

    private function calculatePeriodDepreciation(FixedAsset $asset, Carbon $period, float $accumulated = 0): float
    {
        return match ($asset->depreciation_method) {
            'straight-line' => $this->straightLine($asset),
            'declining-balance' => $this->decliningBalance($asset, $accumulated),
            'units-of-production' => $this->unitsOfProduction($asset, $period),
            default => $this->straightLine($asset),
        };
    }

    private function straightLine(FixedAsset $asset): float
    {
        $totalMonths = $asset->useful_life_years;
        if ($totalMonths <= 0) {
            return 0;
        }

        return (float) $asset->depreciableAmount() / $totalMonths;
    }

    private function decliningBalance(FixedAsset $asset, float $accumulated): float
    {
        $rate = 0.20; // Default 20% annual declining balance rate

        // Annual rate → monthly
        $monthlyRate = 1 - pow(1 - $rate, 1 / 12);

        $nbv = (float) $asset->acquisition_cost - $accumulated;

        return max(0, $nbv * $monthlyRate);
    }

    private function unitsOfProduction(FixedAsset $asset, Carbon $period): float
    {
        // For units-of-production, calculate based on units available
        $totalMonths = $asset->useful_life_years;
        if ($totalMonths <= 0) {
            return 0;
        }

        return (float) $asset->depreciableAmount() / $totalMonths;
    }

    private function generateAssetCode(): string
    {
        return 'FA-'.strtoupper(Str::random(8));
    }
}
