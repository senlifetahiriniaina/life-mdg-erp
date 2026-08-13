<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\PositionFactory;

class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_positions';

    protected $fillable = [
        'title',
        'description',
        'department_id',
        'level',
        'salary_min',
        'salary_max',
        'headcount',
        'status',
    ];

    protected $casts = [
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return PositionFactory::new();
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getFilledCount()
    {
        return $this->employees()->count();
    }

    public function getVacancies()
    {
        return $this->headcount - $this->getFilledCount();
    }
}
