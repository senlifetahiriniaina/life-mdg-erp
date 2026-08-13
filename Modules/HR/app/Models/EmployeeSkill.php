<?php

declare(strict_types=1);

namespace Modules\HR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $skill_id
 * @property int $level
 * @property Carbon|null $certified_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EmployeeSkill extends Model
{
    use HasFactory;
    protected $table = 'hr_employee_skills';

    protected $fillable = ['employee_id', 'skill_id', 'level', 'certified_at', 'expires_at'];

    protected $casts = [
        'certified_at' => 'date',
        'expires_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
