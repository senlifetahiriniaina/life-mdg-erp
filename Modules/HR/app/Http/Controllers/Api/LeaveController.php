<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Resources\LeaveRequestResource;
use Modules\HR\Models\LeaveRequest;

/**
 * @group HR - Leave
 *
 * Submit, approve and reject leave requests.
 */
class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with('employee', 'leaveType', 'approvedBy')
            ->when($request->employee_id, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->year, fn ($q, $v) => $q->whereYear('start_date', $v));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $days = (int) now()->parse($validated['start_date'])
            ->diffInWeekdays(now()->parse($validated['end_date'])) + 1;

        $request = LeaveRequest::create(array_merge($validated, ['days' => $days]));

        return response()->json($request->load('employee', 'leaveType'), 201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($leaveRequest->fresh('employee', 'leaveType', 'approvedBy'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        $leaveRequest->update(array_merge($validated, ['status' => 'rejected']));

        return response()->json(new LeaveRequestResource($leaveRequest->fresh()->load('employee', 'leaveType')));
    }

    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('update', $leaveRequest);

        $validated = $request->validate([
            'employee_id' => ['sometimes', 'exists:hr_employees,id'],
            'leave_type_id' => ['sometimes', 'exists:hr_leave_types,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $start = $validated['start_date'] ?? $leaveRequest->start_date->toDateString();
            $end = $validated['end_date'] ?? $leaveRequest->end_date->toDateString();
            $validated['days'] = (int) now()->parse($start)->diffInWeekdays(now()->parse($end)) + 1;
        }

        $leaveRequest->update($validated);

        return response()->json(new LeaveRequestResource($leaveRequest->fresh()->load('employee', 'leaveType', 'approvedBy')));
    }

    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json(null, 204);
    }
}
