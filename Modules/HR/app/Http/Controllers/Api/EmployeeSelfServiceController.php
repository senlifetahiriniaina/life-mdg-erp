<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Services\AttendanceService;
use Modules\Payroll\Models\Payslip;

/**
 * @group HR - Self Service
 *
 * Employee self-service portal endpoints.
 */
class EmployeeSelfServiceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * Get the authenticated employee's own profile.
     */
    public function me(Request $request): JsonResponse
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
     * Update the authenticated employee's own profile (limited fields).
     */
    public function updateMe(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'emergency_contacts' => 'nullable|array',
            'bank_iban' => 'nullable|string|max:34',
        ]);

        if (isset($data['bank_iban'])) {
            $bankDetails = $employee->bank_details ?? [];
            $bankDetails['iban'] = $data['bank_iban'];
            unset($data['bank_iban']);
            $data['bank_details'] = $bankDetails;
        }

        $employee->update($data);

        return response()->json($employee->fresh([
            'department:id,name',
            'jobPosition:id,title',
        ]));
    }

    /**
     * List the authenticated employee's payslips.
     */
    public function payslips(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $payslips = Payslip::where('employee_id', $employee->id)
            ->select('id', 'period', 'gross_salary', 'net_salary', 'currency', 'status', 'paid_at')
            ->latest('period')
            ->paginate(12);

        return response()->json($payslips);
    }

    /**
     * Get a specific payslip for the authenticated employee.
     */
    public function payslip(Request $request, Payslip $payslip): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ($payslip->employee_id !== $employee->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($payslip);
    }

    /**
     * Get the authenticated employee's attendance.
     */
    public function attendance(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereYear('clock_in', $year)
            ->whereMonth('clock_in', $month)
            ->orderBy('clock_in')
            ->get();

        $monthlyHours = $this->attendanceService->calculateMonthlyHours($employee, $year, $month);

        return response()->json([
            'records' => $records,
            'monthly_hours' => $monthlyHours,
        ]);
    }

    /**
     * Get the authenticated employee's leave balance.
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

        $balances = $types->map(fn (LeaveType $type) => [
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
     * Submit a leave request for the authenticated employee.
     */
    public function submitLeaveRequest(Request $request): JsonResponse
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

    public function leaveRequests(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();
        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType:id,name')
            ->latest()
            ->paginate(15);
        return response()->json($requests);
    }

    public function storeLeaveRequest(Request $request): JsonResponse
    {
        return $this->submitLeaveRequest($request);
    }

    public function showPayslip(Request $request, Payslip $payslip): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        if ((int) $payslip->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($payslip);
    }
}
