<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\Payroll\Models\Payslip;

/**
 * @group HR - Employee Self-Service Portal
 *
 * Read-only access to the current employee's own data.
 * Accessible with the `employee` role.
 */
class EmployeePortalController extends Controller
{
    /**
     * Get the authenticated user's employee profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $employee = Employee::with(
            'department:id,name',
            'jobPosition:id,title',
            'manager:id,first_name,last_name,email'
        )
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json($employee);
    }

    /**
     * Get the employee's leave balance per type.
     */
    public function leaveBalance(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $types = LeaveType::where('is_active', true)->get();

        $taken = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->selectRaw('leave_type_id, SUM(days) as days_taken')
            ->groupBy('leave_type_id')
            ->pluck('days_taken', 'leave_type_id');

        $balances = $types->map(fn ($type) => [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'is_paid' => $type->is_paid,
            'days_per_year' => (float) $type->days_per_year,
            'days_taken' => (float) ($taken[$type->id] ?? 0),
            'days_remaining' => (float) max(0, $type->days_per_year - ($taken[$type->id] ?? 0)),
        ]);

        return response()->json($balances);
    }

    /**
     * Get the employee's leave requests (all years).
     */
    public function leaveRequests(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $requests = LeaveRequest::with('leaveType:id,name,code')
            ->where('employee_id', $employee->id)
            ->latest('start_date')
            ->paginate(20);

        return response()->json($requests);
    }

    /**
     * Submit a new leave request.
     */
    public function submitLeave(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $days = (int) now()->parse($validated['start_date'])
            ->diffInWeekdays(now()->parse($validated['end_date'])) + 1;

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($leave->load('leaveType:id,name'), 201);
    }

    /**
     * Get the employee's payslips.
     */
    public function payslips(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $payslips = Payslip::where('employee_id', $employee->id)
            ->select('id', 'period', 'gross_salary', 'net_salary', 'status', 'paid_at')
            ->latest('period')
            ->paginate(12);

        return response()->json($payslips);
    }

    /**
     * Get a specific payslip detail.
     */
    public function payslipDetail(Request $request, Payslip $payslip): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($payslip->employee_id !== $employee->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($payslip);
    }
}
