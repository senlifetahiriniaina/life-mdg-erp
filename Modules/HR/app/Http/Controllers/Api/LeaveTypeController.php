<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Resources\LeaveTypeResource;
use Modules\HR\Models\LeaveType;

/**
 * @group HR - LeaveType
 *
 * Configure leave types (annual, sick, parental, etc.).
 */
class LeaveTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        // Chantier 32: unconditional company_id scoping — see
        // EmployeeController::index()'s comment for the confirmed empirical
        // finding this closes.
        $query = LeaveType::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }));

        return response()->json(
            LeaveTypeResource::collection($query->orderBy('name')->paginate(min((int) ($request->per_page ?? 25), 100)))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LeaveType::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:hr_leave_types,code'],
            'days_per_year' => ['required', 'integer', 'min:0'],
            'is_paid' => ['boolean'],
            'carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:0'],
        ]);

        // Chantier 32: company_id always derived server-side, never from client input.
        $leaveType = LeaveType::create(array_merge($validated, [
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json(new LeaveTypeResource($leaveType), 201);
    }

    public function show(LeaveType $leaveType): JsonResponse
    {
        $this->authorize('view', $leaveType);

        return response()->json(new LeaveTypeResource($leaveType));
    }

    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        $this->authorize('update', $leaveType);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', "unique:hr_leave_types,code,{$leaveType->id}"],
            'days_per_year' => ['sometimes', 'integer', 'min:0'],
            'is_paid' => ['boolean'],
            'carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:0'],
            'approval_levels' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);

        $leaveType->update($validated);

        return response()->json(new LeaveTypeResource($leaveType->fresh()));
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $this->authorize('delete', $leaveType);

        $leaveType->delete();

        return response()->json(null, 204);
    }
}
