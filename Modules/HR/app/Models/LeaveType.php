<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\HR\Database\Factories\LeaveTypeFactory;

class LeaveType extends Model
{
    use HasFactory;

    protected $table = 'hr_leave_types';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'days_per_year',
        'is_paid',
        'description',
        'status',
        'carry_forward',
        'max_carry_forward_days',
        'approval_levels',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
    ];

    protected static function newFactory()
    {
        return LeaveTypeFactory::new();
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
