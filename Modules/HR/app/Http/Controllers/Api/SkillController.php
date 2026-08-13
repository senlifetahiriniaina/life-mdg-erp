<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeSkill;
use Modules\HR\Models\Skill;

/**
 * @group HR - Skills
 */
class SkillController extends Controller
{
    /**
     * List all skills.
     */
    public function index(Request $request): JsonResponse
    {
        $skills = Skill::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->withCount('employeeSkills')
            ->latest()
            ->paginate(50);

        return response()->json($skills);
    }

    /**
     * Create a new skill.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $skill = Skill::create($data);

        return response()->json($skill, 201);
    }

    /**
     * Show a skill.
     */
    public function show(Skill $skill): JsonResponse
    {
        return response()->json($skill->load('employees'));
    }

    /**
     * Update a skill.
     */
    public function update(Request $request, Skill $skill): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $skill->update($data);

        return response()->json($skill);
    }

    /**
     * Delete a skill.
     */
    public function destroy(Skill $skill): JsonResponse
    {
        $skill->delete();

        return response()->json(null, 204);
    }

    /**
     * List an employee's skills.
     */
    public function employeeSkills(Employee $employee): JsonResponse
    {
        $skills = EmployeeSkill::where('employee_id', $employee->id)
            ->with('skill')
            ->get();

        return response()->json($skills);
    }

    /**
     * Add or update a skill for an employee.
     */
    public function addEmployeeSkill(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'skill_id' => 'required|exists:hr_skills,id',
            'level' => 'required|integer|min:1|max:5',
            'certified_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $empSkill = EmployeeSkill::updateOrCreate(
            ['employee_id' => $employee->id, 'skill_id' => $data['skill_id']],
            $data
        );

        return response()->json($empSkill->load('skill'), 201);
    }
}
