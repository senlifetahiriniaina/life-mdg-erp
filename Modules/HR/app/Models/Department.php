<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\DepartmentFactory;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_departments';

    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
        'budget_allocation',
        'status',
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

    public function positions()
    {
        return $this->hasMany(Position::class, 'department_id');
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
