<?php

namespace Modules\HR\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;

/**
 * Application service for employee lifecycle operations.
 */
class EmployeeService
{
    /**
     * Create an employee after validating required fields and email uniqueness.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(array $data): Employee
    {
        Validator::make($data, [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:hr_employees,email',
            'department_id' => 'required',
            'employment_type' => 'required|string',
        ])->validate();

        $data['employee_number'] = $data['employee_number']
            ?? 'EMP'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $data['hire_date'] = $data['hire_date'] ?? now()->toDateString();
        $data['status'] = $data['status'] ?? 'probation';

        return Employee::create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee;
    }

    public function assignDepartment(Employee $employee, Department $department): bool
    {
        $employee->department_id = $department->id;

        return $employee->save();
    }

    public function activate(Employee $employee): bool
    {
        $employee->status = 'active';

        return $employee->save();
    }

    public function deactivate(Employee $employee): bool
    {
        $employee->status = 'inactive';

        return $employee->save();
    }

    public function getByEmail(string $email): ?Employee
    {
        return Employee::where('email', $email)->first();
    }

    /**
     * Delete an employee. Active employees require force to be deleted.
     *
     * @throws \Exception when deleting an active employee without force
     */
    public function delete(Employee $employee, bool $force = false): bool
    {
        if (! $force && $employee->status === 'active') {
            throw new \Exception('Cannot delete an active employee without force.');
        }

        return (bool) $employee->delete();
    }

    public function getActive(): Collection
    {
        return Employee::where('status', 'active')->get();
    }

    public function getByDepartment(Department $department): Collection
    {
        return Employee::where('department_id', $department->id)->get();
    }
}
