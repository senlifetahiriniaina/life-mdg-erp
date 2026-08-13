<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\HR\Database\Factories\JobPositionFactory;

/**
 * @property int $id
 * @property string $title
 * @property string|null $level
 * @property string|null $description
 * @property array<string,mixed>|null $requirements
 * @property bool $is_active
 * @property int|null $department_id
 */
class JobPosition extends Model
{
    use HasFactory;

    protected static function newFactory(): JobPositionFactory
    {
        return JobPositionFactory::new();
    }

    protected $table = 'hr_job_positions';

    protected $fillable = ['department_id', 'title', 'level', 'description', 'requirements', 'is_active'];

    protected $casts = [
        'requirements' => 'array',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
