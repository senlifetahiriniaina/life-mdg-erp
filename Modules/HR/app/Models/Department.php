<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\HR\Database\Factories\DepartmentFactory;

class Department extends Model
{
    // Chantier 32.17 (HR deep 14-layer audit): no PII on this model, so
    // (unlike Employee) the trait's default full-toArray() capture is
    // safe as-is — see Employee's own docblock for the full rationale.
    use HasFactory, SoftDeletes, RecordsActivity;

    protected $table = 'hr_departments';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'manager_id',
        'budget_allocation',
        'status',
        // Chantier 32.17 (HR deep 14-layer audit): closes the cross-tenant
        // data leak already flagged twice in this session and never fixed
        // (Chantier 19 Lot 2) — see the migration's own docblock.
        'company_id',
    ];

    protected $casts = [
        'budget_allocation' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return DepartmentFactory::new();
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    /**
     * Chantier 32.17: replaces the deleted positions() relation, which
     * pointed at Modules\HR\Models\Position — confirmed dead (Layer 9:
     * zero controller/route consumer anywhere, zero real write path, see
     * the migration that dropped hr_positions in the same chantier). This
     * module's real, live, routed org-structure model is JobPosition.
     */
    public function jobPositions()
    {
        return $this->hasMany(JobPosition::class, 'department_id');
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getEmployeeCount()
    {
        return $this->employees()->count();
    }
}
