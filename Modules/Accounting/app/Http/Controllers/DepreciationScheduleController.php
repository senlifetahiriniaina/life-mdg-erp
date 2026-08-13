<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\DepreciationSchedule;
use Modules\Accounting\Models\DepreciationEntry;

class DepreciationScheduleController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DepreciationSchedule::class);

        $schedules = DepreciationSchedule::with(['fixedAsset', 'depreciationExpenseAccount', 'accumulatedDepreciationAccount'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('depreciation_method'), fn($q) => $q->where('depreciation_method', $request->depreciation_method))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DepreciationSchedule::class);

        $validated = $request->validate([
            'fixed_asset_id' => 'required|exists:fixed_assets,id',
            'depreciation_method' => 'required|in:straight_line,declining_balance,units_of_production,sum_of_years,macrs,custom',
            'useful_life_years' => 'required|integer|min:1',
            'residual_value' => 'required|numeric|min:0',
            'depreciation_start_date' => 'required|date',
            'depreciation_end_date' => 'nullable|date|after:depreciation_start_date',
            'annual_depreciation_amount' => 'required|numeric|min:0',
            'depreciation_expense_account_id' => 'required|exists:gl_accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:gl_accounts,id',
            'depreciation_method_details' => 'nullable|json',
        ]);

        $validated['status'] = 'active';
        $validated['book_value'] = $validated['annual_depreciation_amount'];

        $schedule = DepreciationSchedule::create($validated);

        return response()->json($schedule, 201);
    }

    public function show(DepreciationSchedule $schedule): JsonResponse
    {
        $this->authorize('view', $schedule);

        return response()->json($schedule->load(['fixedAsset', 'entries', 'depreciationExpenseAccount', 'accumulatedDepreciationAccount']));
    }

    public function update(Request $request, DepreciationSchedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'annual_depreciation_amount' => 'numeric|min:0',
            'residual_value' => 'numeric|min:0',
            'status' => 'in:active,paused,completed,retired',
        ]);

        $schedule->update($validated);

        return response()->json($schedule);
    }

    public function record(Request $request, DepreciationSchedule $schedule): JsonResponse
    {
        $this->authorize('record', $schedule);

        $validated = $request->validate([
            'period_date' => 'required|date',
            'journal_entry_id' => 'nullable|exists:journal_entries,id',
        ]);

        $entry = DepreciationEntry::create([
            'depreciation_schedule_id' => $schedule->id,
            'period_date' => $validated['period_date'],
            'depreciation_amount' => $schedule->annual_depreciation_amount,
            'accumulated_depreciation' => $schedule->accumulated_depreciation + $schedule->annual_depreciation_amount,
            'book_value' => $schedule->book_value - $schedule->annual_depreciation_amount,
            'journal_entry_id' => $validated['journal_entry_id'] ?? null,
            'status' => 'recorded',
            'recorded_at' => now(),
        ]);

        return response()->json($entry, 201);
    }

    public function destroy(DepreciationSchedule $schedule): JsonResponse
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return response()->json(null, 204);
    }
}
