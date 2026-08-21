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
     * Chantier 32.17 (HR deep 14-layer audit): extracted from index()'s own
     * inline check so store()/update()/destroy()/statistics() (below —
     * previously written but never routed at all, see the route file's own
     * comment) share the same admin gate rather than being reachable by any
     * 'employee'-role caller once routed. $user->role reads the
     * well-documented phantom users.role column (never populated by any real
     * registration path) — kept only as a defensive first check, the real
     * gate is the hasAnyRole() fallback already used by index().
     */
    private function isAttendanceAdmin($user): bool
    {
        $adminRoles = ['admin', 'hr_manager', 'hr-manager', 'superadmin', 'manager'];

        return in_array($user->role ?? '', $adminRoles, true)
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($adminRoles));
    }

    /**
     * Get attendance summary (used by index method).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->isAttendanceAdmin($user);

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

        // Chantier 32.17 (HR deep 14-layer audit): with('employee') alone
        // eager-loads the FULL raw Employee model — including
        // national_id/passport_number/bank_details when set — bypassing the
        // deliberate redaction every other real employee-facing endpoint in
        // this module goes through (EmployeeResource never exposes those
        // fields at all, even to hr-manager/admin; SelfServiceEmployeeResource
        // only exposes a masked bank detail). Confirmed empirically via
        // tinker that the unscoped relation returned every raw PII column.
        // Scoped to the same minimal, safe field set the sibling
        // AttendanceRecord-backed listAttendance() already uses.
        $query = \Modules\HR\Models\Attendance::query()->with([
            'employee:id,first_name,last_name,department_id',
            'employee.department:id,name',
        ]);

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

    /**
     * Chantier 32.17 (HR deep 14-layer audit): this real, correctly-written
     * method had zero route registered anywhere — confirmed via
     * `php artisan route:list` and by tracing the real, routed admin CRUD
     * page (HR/Attendance/Manage.vue)'s own "Mark Attendance" dialog, which
     * has always POSTed to this exact URL and gotten a 404 in return.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->isAttendanceAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'date'        => 'required|date',
            'status'      => 'required|string',
            'check_in'    => 'nullable|string',
            'check_out'   => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);

        // Chantier 32.17: real columns are check_in_time/check_out_time, not
        // check_in/check_out (see Attendance model's docblock) — mapped here
        // rather than in $fillable so the request/JSON contract this
        // controller and HR/Attendance/Manage.vue both already use never
        // has to change.
        if (isset($data['check_in'])) {
            $data['check_in_time'] = \Carbon\Carbon::parse($data['check_in'])->format('H:i:s');
            unset($data['check_in']);
        }
        if (isset($data['check_out'])) {
            $data['check_out_time'] = \Carbon\Carbon::parse($data['check_out'])->format('H:i:s');
            unset($data['check_out']);
        }

        $record = \Modules\HR\Models\Attendance::create($data);

        return response()->json($record, 201);
    }

    /**
     * Chantier 32.17: same "real method, zero route" gap as store() above.
     * $request->all() is guarded by Attendance::$fillable (mass-assignment),
     * but validated explicitly here to match store()'s stricter contract.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->isAttendanceAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $record = \Modules\HR\Models\Attendance::findOrFail($id);
        $data = $request->validate([
            'employee_id' => 'sometimes|exists:hr_employees,id',
            'date'        => 'sometimes|date',
            'status'      => 'sometimes|string',
            'check_in'    => 'nullable|string',
            'check_out'   => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);
        // Chantier 32.17: same check_in/check_out → check_in_time/
        // check_out_time mapping as store() above.
        if (isset($data['check_in'])) {
            $data['check_in_time'] = \Carbon\Carbon::parse($data['check_in'])->format('H:i:s');
            unset($data['check_in']);
        }
        if (isset($data['check_out'])) {
            $data['check_out_time'] = \Carbon\Carbon::parse($data['check_out'])->format('H:i:s');
            unset($data['check_out']);
        }
        $record->update($data);

        return response()->json($record);
    }

    /**
     * Chantier 32.17: same "real method, zero route" gap — HR/Attendance/
     * Manage.vue's "Delete" button has always 404'd.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->isAttendanceAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

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
