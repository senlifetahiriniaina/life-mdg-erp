<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\BudgetAlertFactory;

class BudgetAlert extends Model
{
    use HasFactory;

    protected $table = 'acc_budget_alerts';

    protected $fillable = [
        'budget_id',
        'budget_line_id',
        'alert_type',
        'status',
        'threshold_percent',
        'current_variance_percent',
        'triggered_at',
    ];

    protected $casts = [
        'threshold_percent' => 'decimal:2',
        'current_variance_percent' => 'decimal:2',
        'triggered_at' => 'datetime',
    ];

    protected static function newFactory(): BudgetAlertFactory
    {
        return BudgetAlertFactory::new();
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }
}
