<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\BiometricDevice;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\AttendanceException;
use Modules\HR\Models\ShiftSchedule;
use Modules\HR\Models\TimeOffRequest;
use Modules\HR\Models\AttendanceAnalytics;

/**
 * @group HR - Attendance & Biometric Integration
 * Manage biometric devices, attendance records, shifts, and time-off requests.
 */
class AttendanceBiometricController extends Controller
{
    /**
     * List biometric devices.
     * @queryParam location string Location filter. Example: Building A
     * @queryParam status string Status filter (active, inactive). Example: active
     */
    public function listDevices(Request $request): JsonResponse
    {
        $this->authorize('manageBiometricDevices', BiometricDevice::class);

        $query = BiometricDevice::query()
            ->when($request->location, fn($q) => $q->where('location', 'like', "%{$request->location}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        return response()->json($query->paginate(20));
    }

    /**
     * Register biometric device.
     * @bodyParam device_id string required Unique device ID. Example: DEV-001
     * @bodyParam device_name string required Device name. Example: Entrance Biometric
     * @bodyParam device_type string required Type. Example: fingerprint
     * @bodyParam location string required Location. Example: Main Entrance
     * @bodyParam ip_address string IP address. Example: 192.168.1.100
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $this->authorize('manageBiometricDevices', BiometricDevice::class);

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'unique:hr_biometric_devices'],
            'device_name' => ['required', 'string'],
            'device_type' => ['required', 'in:fingerprint,facial_recognition,rfid,manual_pin'],
            'location' => ['required', 'string'],
            'building' => ['nullable', 'string'],
            'floor' => ['nullable', 'string'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'string'],
        ]);

        $device = BiometricDevice::create(array_merge($validated, [
            'status' => 'active',
        ]));

        return response()->json($device, 201);
    }

    /**
     * List attendance records.
     * @queryParam employee_id int Employee filter. Example: 5
     * @queryParam start_date date Start date filter. Example: 2025-01-01
     * @queryParam verification_status string Verification status. Example: verified
     */
    public function listAttendance(Request $request): JsonResponse
    {
        $this->authorize('viewAttendance', AttendanceRecord::class);

        $query = AttendanceRecord::with(['employee:id,first_name,last_name', 'device:id,device_name,location'])
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->start_date, fn($q) => $q->where('clock_in', '>=', $request->start_date))
            ->when($request->verification_status, fn($q) => $q->where('verification_status', $request->verification_status))
            ->orderByDesc('clock_in');

        return response()->json($query->paginate(50));
    }

    /**
     * Record clock-in.
     * @bodyParam employee_id int required Employee ID. Example: 5
     * @bodyParam device_id int required Device ID. Example: 1
     * @bodyParam clock_in_method string Method (biometric, rfid, manual). Example: biometric
     * @bodyParam latitude decimal Latitude. Example: 40.7128
     * @bodyParam longitude decimal Longitude. Example: -74.0060
     */
    public function clockIn(Request $request): JsonResponse
    {
        $this->authorize('recordAttendance', AttendanceRecord::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'device_id' => ['required', 'exists:hr_biometric_devices,id'],
            'clock_in_method' => ['required', 'in:biometric,rfid,manual_pin,mobile_app'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $device = BiometricDevice::findOrFail($validated['device_id']);

        // Chantier 8.3: validated() keys were 'latitude'/'longitude' but
        // AttendanceRecord's real columns are 'location_lat'/'location_lng' —
        // previously silently dropped on every clock-in (neither key was even
        // in the model's $fillable at all until this chantier added them).
        $record = AttendanceRecord::create([
            'employee_id' => $validated['employee_id'],
            'device_id' => $validated['device_id'],
            'clock_in_method' => $validated['clock_in_method'],
            'location_lat' => $validated['latitude'] ?? null,
            'location_lng' => $validated['longitude'] ?? null,
            'clock_in' => now(),
            'location' => $device->location,
            'device_name' => $device->device_name,
            'verification_status' => 'pending',
        ]);

        return response()->json($record, 201);
    }

    /**
     * Record clock-out.
     * @urlParam record int required Attendance record ID. Example: 1
     * @bodyParam clock_out_method string Method. Example: biometric
     */
    public function clockOut(Request $request, AttendanceRecord $record): JsonResponse
    {
        $this->authorize('recordAttendance', AttendanceRecord::class);

        if ($record->clock_out !== null) {
            return response()->json(['message' => 'Already clocked out'], 422);
        }

        $record->clockOut();
        return response()->json($record, 200);
    }

    /**
     * Verify attendance record.
     * @urlParam record int required Record ID. Example: 1
     */
    public function verifyRecord(AttendanceRecord $record): JsonResponse
    {
        $this->authorize('verifyRecords', BiometricDevice::class);

        $record->verify();
        return response()->json($record, 200);
    }

    /**
     * List attendance exceptions.
     * @queryParam employee_id int Employee filter. Example: 5
     * @queryParam status string Status filter. Example: flagged
     */
    public function listExceptions(Request $request): JsonResponse
    {
        $this->authorize('viewAttendance', AttendanceRecord::class);

        $query = AttendanceException::with('employee:id,first_name,last_name')
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('attendance_date');

        return response()->json($query->paginate(25));
    }

    /**
     * Approve attendance exception.
     * @urlParam exception int required Exception ID. Example: 1
     */
    public function approveException(Request $request, AttendanceException $exception): JsonResponse
    {
        $this->authorize('approveException', $exception);

        $exception->approve();
        return response()->json($exception, 200);
    }

    /**
     * List shift schedules.
     * @queryParam employee_id int Employee filter. Example: 5
     * @queryParam status string Status filter. Example: active
     */
    public function listShifts(Request $request): JsonResponse
    {
        $query = ShiftSchedule::with('employee:id,first_name,last_name')
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        return response()->json($query->paginate(25));
    }

    /**
     * Create shift schedule.
     * @bodyParam employee_id int required Employee ID. Example: 5
     * @bodyParam shift_name string required Shift name. Example: Morning
     * @bodyParam start_time time required Start time. Example: 09:00:00
     * @bodyParam end_time time required End time. Example: 17:00:00
     * @bodyParam days_of_week array Days (1-7). Example: [1,2,3,4,5]
     */
    public function createShift(Request $request): JsonResponse
    {
        $this->authorize('manageshifts', ShiftSchedule::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'shift_name' => ['required', 'string'],
            'shift_code' => ['nullable', 'string'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after:start_time'],
            'working_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'days_of_week' => ['array'],
            'is_night_shift' => ['boolean'],
            'effective_from' => ['required', 'date'],
        ]);

        $shift = ShiftSchedule::create($validated);
        return response()->json($shift, 201);
    }

    /**
     * List time-off requests.
     * @queryParam employee_id int Employee filter. Example: 5
     * @queryParam status string Status filter. Example: pending
     */
    public function listTimeOffRequests(Request $request): JsonResponse
    {
        $query = TimeOffRequest::with(['employee:id,first_name,last_name', 'approvedBy:id,name'])
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('start_date');

        return response()->json($query->paginate(25));
    }

    /**
     * Request time off.
     * @bodyParam employee_id int required Employee ID. Example: 5
     * @bodyParam request_type string required Type (pto, sick, unpaid). Example: pto
     * @bodyParam start_date date required Start date. Example: 2025-02-01
     * @bodyParam end_date date required End date. Example: 2025-02-05
     * @bodyParam duration_days int Days requested. Example: 5
     */
    public function requestTimeOff(Request $request): JsonResponse
    {
        $this->authorize('requestTimeOff', TimeOffRequest::class);

        $validated = $request->validate([
            'request_type' => ['required', 'in:pto,sick,unpaid,sabbatical,personal,jury_duty'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'is_urgent' => ['boolean'],
        ]);

        $request_obj = TimeOffRequest::create(array_merge($validated, [
            'employee_id' => $request->user()->employee->id ?? $request->employee_id,
            'status' => 'pending',
        ]));

        return response()->json($request_obj, 201);
    }

    /**
     * Approve time-off request.
     * @urlParam timeOff int required Request ID. Example: 1
     */
    public function approveTimeOff(Request $request, TimeOffRequest $timeOff): JsonResponse
    {
        $this->authorize('approveTimeOff', $timeOff);

        $timeOff->approve($request->user()->id);
        return response()->json($timeOff, 200);
    }

    /**
     * View attendance analytics.
     * @queryParam employee_id int Employee filter. Example: 5
     * @queryParam year int Year filter. Example: 2025
     * @queryParam month int Month filter. Example: 1
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', BiometricDevice::class);

        $query = AttendanceAnalytics::with('employee:id,first_name,last_name')
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->when($request->month, fn($q) => $q->where('month', $request->month))
            ->orderByDesc('analytics_date');

        return response()->json($query->paginate(25));
    }
}
