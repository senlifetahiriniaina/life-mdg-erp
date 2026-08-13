<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Database\Factories\ProjectBillingFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property string $billing_type
 * @property float $hourly_rate
 * @property float|null $budget_hours
 * @property float|null $budget_amount
 * @property float $total_billed
 * @property float $total_hours
 * @property string $status
 */
class ProjectBilling extends Model
{
    use HasFactory;

    protected static function newFactory(): ProjectBillingFactory
    {
        return ProjectBillingFactory::new();
    }

    protected $table = 'prj_project_billing';

    protected $fillable = [
        'project_id',
        'billing_type',
        'hourly_rate',
        'budget_hours',
        'budget_amount',
        'total_billed',
        'total_hours',
        'status',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'budget_hours' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'total_billed' => 'decimal:2',
        'total_hours' => 'decimal:2',
        'billing_type' => 'string',
        'status' => 'string',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isHourly(): bool
    {
        return $this->billing_type === 'hourly';
    }

    public function isFixed(): bool
    {
        return $this->billing_type === 'fixed';
    }

    public function remainingBudgetHours(): float
    {
        return (float) ($this->budget_hours ?? 0) - (float) $this->total_hours;
    }

    public function remainingBudgetAmount(): float
    {
        return (float) ($this->budget_amount ?? 0) - (float) $this->total_billed;
    }

    public function utilizationRate(): float
    {
        $budgetHours = (float) ($this->budget_hours ?? 0);
        if ($budgetHours > 0) {
            return ((float) $this->total_hours / $budgetHours) * 100;
        }

        return 0.0;
    }

    public function addBilledAmount(float $amount): void
    {
        $this->total_billed = (float) $this->total_billed + $amount;
        $this->save();
    }

    public function addHours(float $hours): void
    {
        $this->total_hours = (float) $this->total_hours + $hours;
        $this->save();
    }
}
