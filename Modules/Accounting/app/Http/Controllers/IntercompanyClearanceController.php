<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\IntercompanyClearance;

class IntercompanyClearanceController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IntercompanyClearance::class);

        $clearances = IntercompanyClearance::with(['sendingCompany', 'receivingCompany', 'sendingGlAccount', 'receivingGlAccount'])
            ->where(function ($q) {
                $q->where('sending_company_id', $q->user()->company_id)
                    ->orWhere('receiving_company_id', $q->user()->company_id);
            })
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('transaction_type'), fn($q) => $q->where('transaction_type', $request->transaction_type))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->orderBy('transaction_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($clearances);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', IntercompanyClearance::class);

        $validated = $request->validate([
            'sending_company_id' => 'required|exists:companies,id',
            'receiving_company_id' => 'required|exists:companies,id|different:sending_company_id',
            'transaction_date' => 'required|date',
            'transaction_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'sending_gl_account_id' => 'required|exists:gl_accounts,id',
            'receiving_gl_account_id' => 'required|exists:gl_accounts,id',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'documents' => 'nullable|json',
        ]);

        $validated['status'] = 'pending';
        $clearance = IntercompanyClearance::create($validated);

        return response()->json($clearance, 201);
    }

    public function show(IntercompanyClearance $clearance): JsonResponse
    {
        $this->authorize('view', $clearance);

        return response()->json($clearance->load(['sendingCompany', 'receivingCompany', 'sendingGlAccount', 'receivingGlAccount']));
    }

    public function update(Request $request, IntercompanyClearance $clearance): JsonResponse
    {
        $this->authorize('update', $clearance);

        $validated = $request->validate([
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'documents' => 'nullable|json',
            'status' => 'in:pending,matched,cleared,disputed,reversed',
        ]);

        $clearance->update($validated);

        return response()->json($clearance);
    }

    public function clear(Request $request, IntercompanyClearance $clearance): JsonResponse
    {
        $this->authorize('clear', $clearance);

        $clearance->update([
            'status' => 'cleared',
            'cleared_at' => now(),
        ]);

        return response()->json($clearance);
    }

    public function destroy(IntercompanyClearance $clearance): JsonResponse
    {
        $this->authorize('delete', $clearance);

        $clearance->delete();

        return response()->json(null, 204);
    }
}
