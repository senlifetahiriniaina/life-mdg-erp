<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\FiscalYear;

/**
 * Chantier 32 (volet A1) — CRUD réel pour le concept d'année d'exercice
 * comptable, jusque-là un modèle orphelin (voir FiscalYear::class).
 */
class FiscalYearController extends Controller
{
    /** GET /fiscal-years */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FiscalYear::class);

        $years = FiscalYear::forCompany((int) ($request->user()->company_id ?? 0))
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['data' => $years]);
    }

    /** GET /fiscal-years/{fiscalYear} */
    public function show(FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('view', $fiscalYear);

        return response()->json(['data' => $fiscalYear]);
    }

    /** POST /fiscal-years */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FiscalYear::class);

        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'status'     => 'nullable|string|max:50',
        ]);

        $fiscalYear = FiscalYear::create([
            'company_id' => $request->user()->company_id,
            'name'       => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'status'     => $validated['status'] ?? 'open',
            'is_closed'  => false,
        ]);

        return response()->json(['data' => $fiscalYear], 201);
    }

    /** PUT /fiscal-years/{fiscalYear} */
    public function update(Request $request, FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('update', $fiscalYear);

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after:start_date',
            'status'     => 'nullable|string|max:50',
        ]);

        $fiscalYear->update($validated);

        return response()->json(['data' => $fiscalYear->fresh()]);
    }

    /** POST /fiscal-years/{fiscalYear}/close — clôture, jamais un delete */
    public function close(FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('update', $fiscalYear);

        $fiscalYear->update(['is_closed' => true, 'status' => 'closed']);

        return response()->json(['data' => $fiscalYear->fresh()]);
    }

    /** DELETE /fiscal-years/{fiscalYear} */
    public function destroy(FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('delete', $fiscalYear);

        $fiscalYear->delete();

        return response()->json(null, 204);
    }
}
