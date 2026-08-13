<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Modules\HR\Models\Skill;
use Modules\HR\Models\EmployeeSkill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Skill Matrix Service
 * Manages employee competencies and skill assessments.
 * Provides skill-gap analysis and workforce planning insights.
 */
class SkillMatrixService
{
    /**
     * Create or update employee skill
     */
    public function assignSkill(
        int $employeeId,
        int $skillId,
        int $currentLevel,
        ?int $targetLevel = null,
        ?string $notes = null
    ): EmployeeSkill {
        return EmployeeSkill::updateOrCreate(
            ['employee_id' => $employeeId, 'skill_id' => $skillId],
            [
                'current_level' => $currentLevel,
                'target_level' => $targetLevel,
                'assessed_at' => now(),
                'notes' => $notes,
            ]
        );
    }

    /**
     * Get skill matrix for department
     * Returns 2D grid: employees × skills with proficiency levels
     */
    public function getMatrixForDepartment(int $departmentId): array
    {
        $employees = DB::table('employees')
            ->where('department_id', $departmentId)
            ->get(['id', 'first_name', 'last_name']);

        $skills = Skill::all();

        $matrix = [];
        foreach ($employees as $emp) {
            $row = ['employee_id' => $emp->id, 'name' => "{$emp->first_name} {$emp->last_name}"];
            
            foreach ($skills as $skill) {
                $empSkill = EmployeeSkill::where('employee_id', $emp->id)
                    ->where('skill_id', $skill->id)
                    ->first();
                
                $row["skill_{$skill->id}"] = [
                    'level' => $empSkill->current_level ?? 0,
                    'target' => $empSkill->target_level ?? 0,
                    'gap' => ($empSkill->target_level ?? 0) - ($empSkill->current_level ?? 0),
                ];
            }
            
            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * Get skill gap analysis
     * Identifies which skills have gaps vs. targets
     */
    public function getSkillGaps(int $departmentId): Collection
    {
        return DB::table('employee_skills')
            ->join('employees', 'employee_skills.employee_id', '=', 'employees.id')
            ->join('skills', 'employee_skills.skill_id', '=', 'skills.id')
            ->where('employees.department_id', $departmentId)
            ->where(DB::raw('employee_skills.target_level - employee_skills.current_level'), '>', 0)
            ->select([
                'employees.id as employee_id',
                DB::raw("CONCAT(employees.first_name, ' ', employees.last_name) as employee_name"),
                'skills.name as skill_name',
                'employee_skills.current_level',
                'employee_skills.target_level',
                DB::raw('employee_skills.target_level - employee_skills.current_level as gap'),
            ])
            ->orderBy('gap', 'desc')
            ->get();
    }

    /**
     * Get critical skill gaps
     * Skills marked as mandatory but not yet mastered
     */
    public function getCriticalGaps(int $departmentId): Collection
    {
        return DB::table('employee_skills')
            ->join('employees', 'employee_skills.employee_id', '=', 'employees.id')
            ->join('skills', 'employee_skills.skill_id', '=', 'skills.id')
            ->where('employees.department_id', $departmentId)
            ->where('skills.is_mandatory', true)
            ->where('employee_skills.current_level', '<', 3) // Level 3 = Proficient
            ->select([
                'employees.id as employee_id',
                DB::raw("CONCAT(employees.first_name, ' ', employees.last_name) as employee_name"),
                'skills.name as skill_name',
                'employee_skills.current_level',
                'skills.category',
            ])
            ->get();
    }

    /**
     * Get employees by skill
     */
    public function getEmployeesBySkill(int $skillId, int $minLevel = 1): Collection
    {
        return DB::table('employee_skills')
            ->join('employees', 'employee_skills.employee_id', '=', 'employees.id')
            ->where('employee_skills.skill_id', $skillId)
            ->where('employee_skills.current_level', '>=', $minLevel)
            ->select(['employees.id', 'employees.first_name', 'employees.last_name', 'employee_skills.current_level'])
            ->orderBy('employee_skills.current_level', 'desc')
            ->get();
    }

    /**
     * Calculate skill coverage for department
     * What % of required skills are covered at proficient level?
     */
    public function calculateCoveragePct(int $departmentId): float
    {
        $requiredSkills = Skill::where('is_mandatory', true)->count();
        if ($requiredSkills === 0) {
            return 100.0;
        }

        $coveredSkills = DB::table('employee_skills')
            ->join('employees', 'employee_skills.employee_id', '=', 'employees.id')
            ->join('skills', 'employee_skills.skill_id', '=', 'skills.id')
            ->where('employees.department_id', $departmentId)
            ->where('skills.is_mandatory', true)
            ->where('employee_skills.current_level', '>=', 3) // Level 3 = Proficient
            ->distinct('skills.id')
            ->count();

        return ($coveredSkills / $requiredSkills) * 100;
    }

    /**
     * Get skill proficiency distribution
     */
    public function getProficiencyDistribution(int $departmentId): array
    {
        $distribution = [
            'level_0' => 0, // No experience
            'level_1' => 0, // Beginner
            'level_2' => 0, // Intermediate
            'level_3' => 0, // Proficient
            'level_4' => 0, // Expert
        ];

        $counts = DB::table('employee_skills')
            ->join('employees', 'employee_skills.employee_id', '=', 'employees.id')
            ->where('employees.department_id', $departmentId)
            ->selectRaw('current_level, COUNT(*) as count')
            ->groupBy('current_level')
            ->pluck('count', 'current_level');

        foreach ($counts as $level => $count) {
            $distribution["level_{$level}"] = $count;
        }

        return $distribution;
    }

    /**
     * Recommend training programs based on gaps
     */
    public function recommendTraining(int $employeeId): Collection
    {
        return DB::table('employee_skills')
            ->join('skills', 'employee_skills.skill_id', '=', 'skills.id')
            ->where('employee_skills.employee_id', $employeeId)
            ->where(DB::raw('employee_skills.target_level - employee_skills.current_level'), '>', 0)
            ->select([
                'skills.name as skill_name',
                'skills.category',
                'employee_skills.current_level',
                'employee_skills.target_level',
                DB::raw('employee_skills.target_level - employee_skills.current_level as gap'),
            ])
            ->orderBy('gap', 'desc')
            ->get();
    }

    /**
     * Export matrix as CSV
     */
    public function exportMatrix(int $departmentId): string
    {
        $matrix = $this->getMatrixForDepartment($departmentId);
        
        if (empty($matrix)) {
            return '';
        }

        $csv = "Employee,";
        
        // Add skill headers
        $firstRow = $matrix[0];
        $skillKeys = array_filter(array_keys($firstRow), fn($k) => str_starts_with($k, 'skill_'));
        
        foreach ($skillKeys as $skillKey) {
            $csv .= ucfirst(str_replace('_', ' ', $skillKey)) . " (Level),";
        }
        $csv = rtrim($csv, ',') . "\n";

        // Add data rows
        foreach ($matrix as $row) {
            $csv .= $row['name'] . ",";
            
            foreach ($skillKeys as $skillKey) {
                $csv .= $row[$skillKey]['level'] . ",";
            }
            
            $csv = rtrim($csv, ',') . "\n";
        }

        return $csv;
    }
}
