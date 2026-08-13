<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExpenseReport extends Model
{
    use HasFactory;

    protected $table = 'acc_expense_reports';

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'report_number',
        'period_start',
        'period_end',
        'total_amount',
        'total_approved',
        'total_reimbursed',
        'status',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'total_approved' => 'decimal:2',
        'total_reimbursed' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Entity::class, 'company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'employee_id');
    }

    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'acc_expense_report_items', 'expense_report_id', 'expense_id');
    }

    public function getAmountRemaining(): float
    {
        return (float) $this->total_approved - (float) $this->total_reimbursed;
    }
}
