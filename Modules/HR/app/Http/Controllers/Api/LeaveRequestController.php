<?php

namespace Modules\HR\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HR\Http\Requests\StoreLeaveRequestRequest;
use Modules\HR\Http\Resources\LeaveRequestResource;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\HRService;

/**
 * @group Controllers - Leave Request
 *
 * Manage Leave Request resources.
 */
class LeaveRequestController extends Controller
{
    public function __construct(protected HRService $service) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $employee = $request->query('employee_id');
        $type = $request->query('type');
        $startDateBefore = $request->query('start_date_before');
        $startDateAfter = $request->query('start_date_after');
        $perPage = $request->query('per_page', 15);

        $query = LeaveRequest::with('employee', 'leaveType');

        if ($status) {
            $query->where('status', $status);
        }

        if ($employee) {
            $query->where('employee_id', $employee);
        }

        if ($type) {
            $query->where(function ($q) use ($type) {
                $q->where('type', $type)
                    ->orWhereHas('leaveType', fn ($q2) => $q2->where('code', strtoupper($type)));
            });
        }

        if ($startDateBefore) {
            $query->where('start_date', '<', $startDateBefore);
        }

        if ($startDateAfter) {
            $query->where('start_date', '>=', $startDateAfter);
        }

        $requests = $query->paginate($perPage);

        return LeaveRequestResource::collection($requests);
    }

    public function store(StoreLeaveRequestRequest $request)
    {
        $data = $request->validated();

        // Set employee to authenticated user's employee (unless admin provides employee_id)
        $user = $request->user();
        if (! isset($data['employee_id']) || empty($data['employee_id'])) {
            if (! $user->employee) {
                // Auto-create an employee record for the user if none exists
                $employee = \Modules\HR\Models\Employee::create([
                    'user_id' => $user->id,
                    'first_name' => $user->name ?? 'Unknown',
                    'last_name' => '',
                    'email' => $user->email,
                    'employee_number' => 'AUTO-' . $user->id,
                    'hire_date' => now()->toDateString(),
                    'employment_type' => 'full_time',
                    'status' => 'active',
                ]);
                $data['employee_id'] = $employee->id;
            } else {
                $data['employee_id'] = $user->employee->id;
            }
        }

        // Resolve type string to leave_type_id if needed
        if (empty($data['leave_type_id']) && !empty($data['type'])) {
            $typeCode = strtoupper($data['type']);
            $leaveType = \Modules\HR\Models\LeaveType::where('code', $typeCode)->first()
                ?? \Modules\HR\Models\LeaveType::create([
                    'code' => $typeCode,
                    'name' => ucfirst($data['type']),
                    'days_per_year' => config('hr.default_leave_days_per_year', 30),
                    'is_paid' => true,
                ]);
            $data['leave_type_id'] = $leaveType->id;
        }

        // Calculate days if not provided
        if (! isset($data['days'])) {
            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);
            $data['days'] = abs((int) $start->diffInDays($end)) + 1;
        }

        // Calculate days_requested if not provided
        if (! isset($data['days_requested'])) {
            $data['days_requested'] = $data['days'] ?? 0;
        }

        $leave = $this->service->requestLeave($data);
        $leave->load('leaveType');

        return response()->json(new LeaveRequestResource($leave), 201);
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $leaveRequest->load('employee', 'leaveType', 'approver');

        return new LeaveRequestResource($leaveRequest);
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending leave requests can be updated.'], 403);
        }

        $data = $request->validate([
            'type' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
        ]);

        if (isset($data['start_date']) || isset($data['end_date'])) {
            $start = Carbon::parse($data['start_date'] ?? $leaveRequest->start_date);
            $end = Carbon::parse($data['end_date'] ?? $leaveRequest->end_date);
            $data['days'] = abs((int) $start->diffInDays($end)) + 1;
            $data['days_requested'] = $data['days'];
        }

        $leaveRequest->update(array_filter($data, fn ($v) => $v !== null));

        return new LeaveRequestResource($leaveRequest->fresh());
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending leave requests can be cancelled.'], 403);
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return response()->noContent();
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending leave requests can be approved.'], 403);
        }

        $user = auth()->user();
        $approverId = $user->employee?->id ?? $leaveRequest->employee_id;
        $approved = $this->service->approveLeave(
            $leaveRequest,
            $approverId,
            $request->input('notes', ''),
            $user->getRoleNames()->first()
        );

        return new LeaveRequestResource($approved);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $user = auth()->user();
        $rejecterId = $user->employee?->id ?? $leaveRequest->employee_id;
        $rejected = $this->service->rejectLeave(
            $leaveRequest,
            $rejecterId,
            $request->input('rejection_reason', $request->input('notes', '')),
            $user->getRoleNames()->first()
        );

        return new LeaveRequestResource($rejected);
    }

    public function pending()
    {
        $pending = $this->service->getPendingLeaveRequests();

        return LeaveRequestResource::collection($pending);
    }
}
