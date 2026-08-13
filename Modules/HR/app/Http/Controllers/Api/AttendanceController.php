<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\HR\Services\AttendanceService;

/**
 * @group HR - Attendance
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    /**
     * Clock in the authenticated employee.
     */
    public function clockIn(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'type' => 'nullable|in:regular,overtime,remote',
            'notes' => 'nullable|string',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
        ]);

        $record = $this->service->clockIn($employee, $data);

        return response()->json($record, 201);
    }

    /**
     * Clock out the authenticated employee.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $record = $this->service->clockOut($employee);

        return response()->json($record);
    }

    /**
     * Get attendance records for an employee (calendar view).
     */
    public function employeeAttendance(Request $request, Employee $employee): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereYear('clock_in', $year)
            ->whereMonth('clock_in', $month)
            ->orderBy('clock_in')
            ->get()
            ->map(fn (AttendanceRecord $r) => [
                'id' => $r->id,
                'clock_in' => $r->clock_in->toIso8601String(),
                'clock_out' => $r->clock_out?->toIso8601String(),
                'break_minutes' => $r->break_minutes,
                'type' => $r->type,
                'worked_hours' => $r->worked_hours,
                'notes' => $r->notes,
            ]);

        $monthlyHours = $this->service->calculateMonthlyHours($employee, $year, $month);

        return response()->json([
            'records' => $records,
            'monthly_hours' => $monthlyHours,
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * Get attendance records for the authenticated employee.
     */
    public function ownAttendance(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        return $this->employeeAttendance($request, $employee);
    }

    /**
     * Get the current clock-in status for the authenticated employee.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if ($employee === null) {
            return response()->json(['clocked_in' => false, 'record' => null]);
        }

        $open = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        return response()->json([
            'clocked_in' => $open !== null,
            'record' => $open,
        ]);
    }

    /**
     * Get attendance summary (used by index method).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $adminRoles = ['admin', 'hr_manager', 'hr-manager', 'superadmin', 'manager'];
        $isAdmin = in_array($user->role ?? '', $adminRoles, true)
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($adminRoles));

        // Non-admin users can only view their own attendance
        if (! $isAdmin) {
            $employee = \Modules\HR\Models\Employee::where('user_id', $user->id)->first();
            if (! $employee) {
                return response()->json(['data' => []], 200);
            }
            $requestedEmployeeId = $request->input('employee_id');
            if ($requestedEmployeeId && (int) $requestedEmployeeId !== $employee->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $query = \Modules\HR\Models\Attendance::query()->with('employee');

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'date'        => 'required|date',
            'status'      => 'required|string',
            'check_in'    => 'nullable|string',
            'check_out'   => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);

        // Normalize time fields to HH:MM:SS format
        if (isset($data['check_in'])) {
            $data['check_in'] = \Carbon\Carbon::parse($data['check_in'])->format('H:i:s');
        }
        if (isset($data['check_out'])) {
            $data['check_out'] = \Carbon\Carbon::parse($data['check_out'])->format('H:i:s');
        }

        $record = \Modules\HR\Models\Attendance::create($data);

        return response()->json($record, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = \Modules\HR\Models\Attendance::findOrFail($id);
        $data = $request->all();
        if (isset($data['check_in'])) {
            $data['check_in'] = \Carbon\Carbon::parse($data['check_in'])->format('H:i:s');
        }
        if (isset($data['check_out'])) {
            $data['check_out'] = \Carbon\Carbon::parse($data['check_out'])->format('H:i:s');
        }
        $record->update($data);

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        \Modules\HR\Models\Attendance::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function statistics(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        $query = \Modules\HR\Models\Attendance::whereDate('date', $date);
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
        }

        $records = $query->get();
        $total = Employee::count();

        return response()->json([
            'date'           => $date,
            'total'          => $total,
            'present'        => $records->where('status', 'present')->count(),
            'absent'         => $records->where('status', 'absent')->count(),
            'late'           => $records->where('status', 'late')->count(),
            'on_leave'       => $records->where('status', 'on_leave')->count(),
            'attendance_rate' => $total > 0
                ? round($records->where('status', 'present')->count() / $total * 100, 1)
                : 0,
        ]);
    }
}
