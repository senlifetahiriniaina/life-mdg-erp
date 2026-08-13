<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\VatRate;

/**
 * @group Controllers - Vat Rate
 *
 * Manage Vat Rate resources.
 */
class VatRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vatRates = VatRate::query()
            ->when($request->country_code, fn ($q) => $q->where('country_code', $request->country_code))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderBy('country_code')
            ->get();

        return response()->json($vatRates);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'applies_from' => ['required', 'date'],
            'applies_to' => ['nullable', 'date', 'after_or_equal:applies_from'],
            'type' => ['required', 'in:standard,reduced,zero,exempt'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $vatRate = VatRate::create($validated);

        return response()->json($vatRate, 201);
    }

    public function show(VatRate $vatRate): JsonResponse
    {
        return response()->json($vatRate);
    }

    public function update(Request $request, VatRate $vatRate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'applies_from' => ['sometimes', 'date'],
            'applies_to' => ['nullable', 'date'],
            'type' => ['sometimes', 'in:standard,reduced,zero,exempt'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $vatRate->update($validated);

        return response()->json($vatRate->fresh());
    }

    public function destroy(VatRate $vatRate): JsonResponse
    {
        $vatRate->delete();

        return response()->json(null, 204);
    }
}
