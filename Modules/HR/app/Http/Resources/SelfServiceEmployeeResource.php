<?php

declare(strict_types=1);

namespace Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee profile as returned to the employee themselves (self-service /
 * portal endpoints). Unlike EmployeeResource (admin-facing), this includes
 * emergency_contacts (needed for the employee to review/confirm what they
 * saved) but MUST NEVER expose raw bank_details/national_id/passport_number
 * — those are PII/PCI material that a raw response()->json($employee) call
 * would otherwise auto-decrypt and serialize in full.
 */
class SelfServiceEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
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
            'emergency_contacts' => $this->emergency_contacts,
            'masked_bank_details' => $this->masked_bank_details,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'job_position_id' => $this->job_position_id,
            // Chantier 8.3: unlike EmployeeResource's 'position' key, the real
            // resources/js/Pages/HR/Portal.vue consumes 'job_position.title' —
            // match what the live frontend actually reads.
            'job_position' => new PositionResource($this->whenLoaded('jobPosition')),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'name' => trim(($this->manager?->first_name ?? '').' '.($this->manager?->last_name ?? '')),
                'email' => $this->manager?->email,
            ]),
            'hire_date' => $this->hire_date,
            'years_of_service' => $this->getYearsOfService(),
            'employment_type' => $this->employment_type,
            'job_title' => $this->job_title ?? $this->whenLoaded('jobPosition', fn () => $this->jobPosition?->title),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
