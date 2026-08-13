<?php

declare(strict_types=1);

namespace Modules\HR\Observers;

use App\Events\HrEmployeeUpdated;
use Modules\HR\Models\Employee;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        HrEmployeeUpdated::dispatch($employee, 'created');
    }

    public function updated(Employee $employee): void
    {
        HrEmployeeUpdated::dispatch($employee, 'updated');
    }
}
