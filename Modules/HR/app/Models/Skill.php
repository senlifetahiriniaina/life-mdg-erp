<?php

declare(strict_types=1);

namespace Modules\HR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\HR\Database\Factories\SkillFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Skill extends Model
{
    use HasFactory;

    protected static function newFactory(): SkillFactory
    {
        return SkillFactory::new();
    }

    protected $table = 'hr_skills';

    protected $fillable = ['company_id', 'name', 'category', 'description'];

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hr_employee_skills')
            ->withPivot('level', 'certified_at', 'expires_at')
            ->withTimestamps();
    }
}
