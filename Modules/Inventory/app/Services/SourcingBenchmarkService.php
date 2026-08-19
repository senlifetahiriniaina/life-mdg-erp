<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SourcingBenchmark;
use Modules\Shared\Models\Currency;

/**
 * Chantier 17 — compares the company's internal catalogue price against
 * price observations logged from external China/Europe textile sourcing
 * sites (XM Textiles, Klopman, Carrington Textiles, Marina Textil, TenCate
 * Protective Fabrics), to build a cost-structure picture as those
 * observations accumulate over time.
 *
 * Deliberately NOT a live scraper: this app has no API access or scraping
 * infrastructure for these sites (fragile, ToS-sensitive, and prices change
 * constantly) — the user's own framing ("cela donnerait idée sur la
 * structure de coûts au fur et à mesure où nous utiliserons le système")
 * describes an accumulating log, which is what this is. `record()` is the
 * only write path; everything else is read-only aggregation.
 */
class SourcingBenchmarkService
{
    /**
     * @param  array{product_id: ?int, product_template_id: ?int, material_label: ?string, source: string, source_name_other: ?string, source_url: ?string, unit_price: float, currency: string, unit: ?string, quantity_reference: ?float, observed_at: string, notes: ?string}  $data
     */
    public function record(array $data, ?int $userId): SourcingBenchmark
    {
        if (empty($data['product_id']) && empty($data['product_template_id']) && empty($data['material_label'])) {
            throw new \InvalidArgumentException('Une observation de prix doit être rattachée à un produit, un template, ou porter un libellé de matière.');
        }

        return SourcingBenchmark::create([...$data, 'created_by' => $userId]);
    }

    public function history(?int $productId = null, ?int $productTemplateId = null): Collection
    {
        return SourcingBenchmark::query()
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($productTemplateId, fn ($q) => $q->where('product_template_id', $productTemplateId))
            ->orderByDesc('observed_at')
            ->get();
    }

    public function delete(SourcingBenchmark $benchmark): void
    {
        $benchmark->delete();
    }

    /**
     * Real cost-structure comparison for one catalogue product: converts
     * every observation to the product's own currency, groups by source
     * (average/min/max), and reports the variance vs the product's real
     * cost_price — plus a monthly trend so the picture sharpens as more
     * observations accumulate.
     *
     * @return array{internal_cost: float, internal_currency: string, by_source: array, monthly_trend: array, observation_count: int}
     */
    public function compare(Product $product): array
    {
        $observations = SourcingBenchmark::where('product_id', $product->id)
            ->orderBy('observed_at')
            ->get();

        $targetCurrency = $product->currency ?: 'MGA';
        $internalCost = (float) ($product->cost_price ?? 0);

        $converted = $observations->map(function (SourcingBenchmark $obs) use ($targetCurrency) {
            $convertedPrice = $this->convert((float) $obs->unit_price, $obs->currency, $targetCurrency);

            return [
                'id' => $obs->id,
                'source' => $obs->source,
                'source_label' => $obs->sourceLabel(),
                'observed_at' => $obs->observed_at->toDateString(),
                'original_price' => (float) $obs->unit_price,
                'original_currency' => $obs->currency,
                'converted_price' => $convertedPrice,
                'unit' => $obs->unit,
            ];
        });

        $bySource = $converted
            ->filter(fn ($row) => $row['converted_price'] !== null)
            ->groupBy('source')
            ->map(function (Collection $rows, string $source) use ($internalCost) {
                $prices = $rows->pluck('converted_price');
                $avg = round((float) $prices->avg(), 4);

                return [
                    'source' => $source,
                    'source_label' => $rows->first()['source_label'],
                    'observation_count' => $rows->count(),
                    'avg_price' => $avg,
                    'min_price' => round((float) $prices->min(), 4),
                    'max_price' => round((float) $prices->max(), 4),
                    'variance_pct' => $internalCost > 0 ? round((($avg - $internalCost) / $internalCost) * 100, 2) : null,
                ];
            })
            ->values()
            ->all();

        $monthlyTrend = $converted
            ->filter(fn ($row) => $row['converted_price'] !== null)
            ->groupBy(fn ($row) => substr($row['observed_at'], 0, 7))
            ->map(fn (Collection $rows, string $month) => [
                'month' => $month,
                'avg_price' => round((float) $rows->pluck('converted_price')->avg(), 4),
            ])
            ->values()
            ->sortBy('month')
            ->values()
            ->all();

        return [
            'internal_cost' => $internalCost,
            'internal_currency' => $targetCurrency,
            'by_source' => $bySource,
            'monthly_trend' => $monthlyTrend,
            'observation_count' => $observations->count(),
        ];
    }

    /**
     * Same conversion math as Modules\Shared\Http\Controllers\Api\CurrencyController::convert()
     * (exchange_rate_to_usd is "units of currency per 1 USD"), reused directly
     * against the Currency model rather than round-tripping through HTTP.
     * Returns null (not a guessed value) when either currency has no real
     * seeded rate, matching this app's fallback-first design — a missing
     * rate should surface as "not comparable," not a silently wrong number.
     */
    private function convert(float $amount, string $from, string $to): ?float
    {
        if ($from === $to) {
            return round($amount, 4);
        }

        $fromCurrency = Currency::where('code', strtoupper($from))->first();
        $toCurrency = Currency::where('code', strtoupper($to))->first();

        if (!$fromCurrency || !$toCurrency || $fromCurrency->exchange_rate_to_usd === null || $toCurrency->exchange_rate_to_usd === null) {
            return null;
        }

        $amountInUsd = $amount / (float) $fromCurrency->exchange_rate_to_usd;

        return round($amountInUsd * (float) $toCurrency->exchange_rate_to_usd, 4);
    }
}
