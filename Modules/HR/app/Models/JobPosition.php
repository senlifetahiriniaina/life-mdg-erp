<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\RecordsActivity;
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
    // Chantier 32.17 (HR deep 14-layer audit): no PII on this model — see
    // Employee's own docblock for the full RecordsActivity rationale.
    use HasFactory, RecordsActivity;

    protected static function newFactory(): JobPositionFactory
    {
        return JobPositionFactory::new();
    }

    protected $table = 'hr_job_positions';

    // Chantier 32.17 (HR deep 14-layer audit): 'company_id' closes the same
    // cross-tenant leak fixed on Employee/Department in the same chantier.
    protected $fillable = ['department_id', 'title', 'level', 'description', 'requirements', 'is_active', 'company_id'];

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
