<?php

namespace Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'age' => $this->getAge(),
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'address' => $this->address,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'job_position_id' => $this->job_position_id,
            // Chantier 32.17 (HR deep 14-layer audit): this used to wrap
            // PositionResource around the jobPosition relation — a Resource
            // shaped for the confirmed-dead Position model (salary_min/
            // salary_max/headcount/status, none of which exist on the real
            // JobPosition being passed in, so they always rendered as null)
            // rather than the real JobPositionResource already used
            // correctly by JobPositionController. Repointed.
            'position' => new JobPositionResource($this->whenLoaded('jobPosition')),
            'hire_date' => $this->hire_date,
            'years_of_service' => $this->getYearsOfService(),
            'employment_type' => $this->employment_type,
            'job_title' => $this->job_title ?? $this->whenLoaded('jobPosition', fn () => $this->jobPosition?->title),
            'manager_id' => $this->manager_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
