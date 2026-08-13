<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\RevenueContract;
use Modules\Accounting\Models\RevenueRecognitionSchedule;

class RevenueContractController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RevenueContract::class);

        $contracts = RevenueContract::with(['customer', 'recognitionSchedules', 'liability'])
            ->whereHas('customer', fn($q) => $q->where('company_id', $request->user()->company_id))
            ->when($request->filled('search'), fn($q) => $q->where('contract_number', 'like', '%' . $request->search . '%')
                ->orWhere('contract_type', 'like', '%' . $request->search . '%'))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('recognition_method'), fn($q) => $q->where('revenue_recognition_method', $request->recognition_method))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($contracts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', RevenueContract::class);

        $validated = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'contract_number' => 'required|string|unique:revenue_contracts',
            'contract_type' => 'required|string',
            'customer_id' => 'required|exists:customers,id',
            'contract_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'contract_value' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'performance_obligation_type' => 'required|in:single_performance,multiple_performance,time_based,outcome_based',
            'performance_obligations' => 'nullable|json',
            'revenue_recognition_method' => 'required|in:point_in_time,over_time,milestone,proportional,custom',
            'recognition_policy' => 'nullable|string',
            'has_variable_consideration' => 'boolean',
            'variable_consideration_estimate' => 'nullable|numeric|min:0',
            'variable_consideration_method' => 'nullable|in:expected_value,most_likely_amount',
            'variable_consideration_constraint_date' => 'nullable|date',
        ]);

        $contract = RevenueContract::create($validated);

        return response()->json($contract, 201);
    }

    public function show(RevenueContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        return response()->json($contract->load(['customer', 'recognitionSchedules', 'liability']));
    }

    public function update(Request $request, RevenueContract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        $validated = $request->validate([
            'contract_type' => 'string',
            'contract_value' => 'numeric|min:0',
            'recognition_policy' => 'nullable|string',
            'has_variable_consideration' => 'boolean',
            'variable_consideration_estimate' => 'nullable|numeric|min:0',
            'variable_consideration_method' => 'nullable|in:expected_value,most_likely_amount',
            'has_contract_modification' => 'boolean',
            'status' => 'in:draft,active,partial_recognized,fully_recognized,cancelled',
        ]);

        $contract->update($validated);

        return response()->json($contract);
    }

    public function recognize(Request $request, RevenueContract $contract): JsonResponse
    {
        $this->authorize('recognize', $contract);

        $validated = $request->validate([
            'recognition_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'gl_account_id' => 'required|exists:gl_accounts,id',
            'description' => 'nullable|string',
            'asc606_details' => 'nullable|json',
        ]);

        $schedule = RevenueRecognitionSchedule::create([
            'revenue_contract_id' => $contract->id,
            ...$validated,
            'status' => 'scheduled',
        ]);

        return response()->json($schedule, 201);
    }

    public function destroy(RevenueContract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);

        $contract->delete();

        return response()->json(null, 204);
    }
}
