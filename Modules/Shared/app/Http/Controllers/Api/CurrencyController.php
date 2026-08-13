<?php

declare(strict_types=1);

namespace Modules\Shared\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shared\Models\Currency;

class CurrencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currencies = Currency::query()
            ->when(!$request->boolean('include_inactive'), fn($q) => $q->active())
            ->when($request->filled('region'), fn($q) => $q->where('region', $request->region))
            ->when($request->boolean('cfa_only'), fn($q) => $q->cfa())
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $currencies]);
    }

    public function show(string $code): JsonResponse
    {
        $currency = Currency::where('code', strtoupper($code))->firstOrFail();

        return response()->json(['data' => $currency]);
    }

    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from'   => 'required|string|size:3',
            'to'     => 'required|string|size:3',
        ]);

        $from = Currency::where('code', strtoupper($data['from']))->firstOrFail();
        $to   = Currency::where('code', strtoupper($data['to']))->firstOrFail();

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
}
