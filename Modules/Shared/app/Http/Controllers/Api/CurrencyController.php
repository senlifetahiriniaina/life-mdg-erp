<?php

declare(strict_types=1);

namespace Modules\Shared\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Shared\Models\Currency;

class CurrencyController extends Controller
{
    // Chantier 32.8 (14-layer deep audit, layer 14f perf): same finding as
    // CountryController — shared_currencies is static reference data
    // (populated once by DefaultDataSeeder, exchange_rate_to_usd is
    // explicitly documented as an illustrative rate an admin reviews
    // periodically, not a live feed) but every call — including convert(),
    // which does 2 fresh lookups on every single request — re-queried it
    // with zero caching. A genuine, confirmed N+1 was found in the same
    // pass on the caller side (Modules\Inventory\Services\
    // {CostingSheetService,SourcingBenchmarkService} both loop their own
    // lines/observations and call ::where('code', ...)->first() per
    // iteration) — that loop itself lives outside this module and isn't
    // fixed here, but caching the single-currency lookup by code here
    // still directly benefits it, since the repeated cost across a whole
    // request now collapses to a single real query per distinct currency
    // code instead of two raw queries per line.
    private const CACHE_TTL = 3600;

    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');
        $region = $request->filled('region') ? $request->region : null;
        $cfaOnly = $request->boolean('cfa_only');
        $cacheKey = "shared:currencies:index:inactive={$includeInactive}:region={$region}:cfa={$cfaOnly}";

        $currencies = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($includeInactive, $region, $cfaOnly) {
            return Currency::query()
                ->when(!$includeInactive, fn($q) => $q->active())
                ->when($region, fn($q) => $q->where('region', $region))
                ->when($cfaOnly, fn($q) => $q->cfa())
                ->orderBy('code')
                ->get();
        });

        return response()->json(['data' => $currencies]);
    }

    public function show(string $code): JsonResponse
    {
        $currency = $this->findByCodeCached($code);

        abort_if(!$currency, 404);

        return response()->json(['data' => $currency]);
    }

    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from'   => 'required|string|size:3',
            'to'     => 'required|string|size:3',
        ]);

        $from = $this->findByCodeCached($data['from']);
        $to   = $this->findByCodeCached($data['to']);

        abort_if(!$from || !$to, 404);

        if ($from->exchange_rate_to_usd === null || $to->exchange_rate_to_usd === null) {
            return response()->json([
                'error' => 'Exchange rate not available for one or both currencies.',
            ], 422);
        }

        $amountInUsd   = (float) $data['amount'] / (float) $from->exchange_rate_to_usd;
        $convertedAmount = $amountInUsd * (float) $to->exchange_rate_to_usd;

        return response()->json([
            'data' => [
                'from_currency'    => $from->code,
                'to_currency'      => $to->code,
                'original_amount'  => $data['amount'],
                'converted_amount' => round($convertedAmount, $to->decimals),
                'rate'             => round((float) $to->exchange_rate_to_usd / (float) $from->exchange_rate_to_usd, 6),
                'rate_date'        => $from->exchange_rate_updated_at?->toDateString(),
            ],
        ]);
    }

    private function findByCodeCached(string $code): ?Currency
    {
        $code = strtoupper($code);

        return Cache::remember("shared:currencies:code:{$code}", self::CACHE_TTL, function () use ($code) {
            return Currency::where('code', $code)->first();
        });
    }
}
