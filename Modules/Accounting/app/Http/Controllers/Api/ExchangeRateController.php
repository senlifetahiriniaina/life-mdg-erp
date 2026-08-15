<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\CurrencyGainLoss;
use Modules\Accounting\Models\ExchangeRate;

/**
 * @group Accounting
 *
 * Manage ExchangeRate resources in Accounting module.
 */
class ExchangeRateController extends Controller
{
    public function index(): JsonResponse
    {
        $rates = ExchangeRate::latest('date')->paginate(50);

        return response()->json($rates);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_currency' => 'required|string|size:3',
            'target_currency' => 'required|string|size:3',
            'rate' => 'required|numeric|min:0.000001',
            'source' => 'nullable|in:manual,api',
            'date' => 'required|date',
        ]);

        /** @var ExchangeRate $rate */
        $rate = ExchangeRate::create($data);

        return response()->json($rate, 201);
    }

    public function show(ExchangeRate $exchangeRate): JsonResponse
    {
        return response()->json($exchangeRate);
    }

    public function update(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $data = $request->validate([
            'rate' => 'nullable|numeric|min:0.000001',
            'source' => 'nullable|in:manual,api',
            'date' => 'nullable|date',
        ]);

        $exchangeRate->update($data);

        return response()->json($exchangeRate);
    }

    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->delete();

        return response()->json(null, 204);
    }

    /**
     * Mock API fetch for exchange rates.
     */
    public function fetch(Request $request): JsonResponse
    {
        $pairs = [
            ['base' => 'EUR', 'target' => 'USD', 'rate' => 1.085],
            ['base' => 'EUR', 'target' => 'GBP', 'rate' => 0.856],
            ['base' => 'EUR', 'target' => 'CHF', 'rate' => 0.968],
            ['base' => 'EUR', 'target' => 'JPY', 'rate' => 163.42],
        ];

        $created = [];
        $today = now()->toDateString();

        foreach ($pairs as $pair) {
            $rate = ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $pair['base'],
                    'target_currency' => $pair['target'],
                    'date' => $today,
                ],
                [
                    'rate' => $pair['rate'],
                    'source' => 'api',
                ]
            );
            $created[] = $rate;
        }

        return response()->json(['fetched' => count($created), 'rates' => $created]);
    }

    public function gainLosses(): JsonResponse
    {
        $realized = CurrencyGainLoss::where('realized', true)->sum('gain_loss');
        $unrealized = CurrencyGainLoss::where('realized', false)->sum('gain_loss');

        $records = CurrencyGainLoss::with('invoice')->latest()->paginate(20);

        return response()->json([
            'records' => $records,
            'total_realized' => (string) round((float) $realized, 2),
            'total_unrealized' => (string) round((float) $unrealized, 2),
        ]);
    }
}
