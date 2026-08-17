<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\AssetImpairment;

class AssetImpairmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetImpairment::class);

        $impairments = AssetImpairment::with(['fixedAsset', 'journalEntry'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('impairment_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('impairment_date', '<=', $request->date_to))
            ->orderBy('impairment_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($impairments);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AssetImpairment::class);

        $validated = $request->validate([
            'fixed_asset_id' => 'required|exists:acc_fixed_assets,id',
            'impairment_date' => 'required|date',
            'original_cost' => 'required|numeric|min:0',
            'accumulated_depreciation_before' => 'required|numeric|min:0',
            'book_value_before' => 'required|numeric|min:0',
            'fair_value' => 'required|numeric|min:0',
            'impairment_reason' => 'required|string',
        ]);

        $validated['impairment_loss'] = max(0, $validated['book_value_before'] - $validated['fair_value']);
        $validated['new_book_value'] = $validated['fair_value'];
        $validated['status'] = 'draft';

        $impairment = AssetImpairment::create($validated);

        return response()->json($impairment, 201);
    }

    public function show(AssetImpairment $impairment): JsonResponse
    {
        $this->authorize('view', $impairment);

        return response()->json($impairment->load(['fixedAsset', 'journalEntry']));
    }

    public function update(Request $request, AssetImpairment $impairment): JsonResponse
    {
        $this->authorize('update', $impairment);

        $validated = $request->validate([
            'fair_value' => 'numeric|min:0',
            'impairment_reason' => 'string',
        ]);

        if (isset($validated['fair_value'])) {
            $validated['impairment_loss'] = max(0, $impairment->book_value_before - $validated['fair_value']);
            $validated['new_book_value'] = $validated['fair_value'];
        }

        $impairment->update($validated);

        return response()->json($impairment);
    }

    public function approve(Request $request, AssetImpairment $impairment): JsonResponse
    {
        $this->authorize('approve', $impairment);

        $impairment->update(['status' => 'approved']);

        return response()->json($impairment);
    }

    public function record(Request $request, AssetImpairment $impairment): JsonResponse
    {
        $this->authorize('record', $impairment);

        $validated = $request->validate([
            'journal_entry_id' => 'required|exists:acc_journal_entries,id',
        ]);

        $impairment->update([
            'journal_entry_id' => $validated['journal_entry_id'],
            'status' => 'recorded',
        ]);

        return response()->json($impairment);
    }

    public function destroy(AssetImpairment $impairment): JsonResponse
    {
        $this->authorize('delete', $impairment);

        $impairment->delete();

        return response()->json(null, 204);
    }
}
