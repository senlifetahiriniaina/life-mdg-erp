<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use AuditableActions, HasFactory;

    protected $table = 'acc_budgets';

    protected $auditableFields = ['status', 'total_budget', 'approved_by_id', 'approved_at'];
    protected $auditModule = 'Accounting';

    protected $fillable = [
        'company_id',
        'parent_budget_id',
        'name',
        'description',
        'budget_period_start',
        'budget_period_end',
        'fiscal_year',
        'fiscal_year_start',
        'fiscal_year_end',
        'total_budget',
        'total_spent',
        'status',
        'department',
        'currency',
        'created_by',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'budget_period_start' => 'date',
        'budget_period_end' => 'date',
        'fiscal_year' => 'integer',
        'fiscal_year_start' => 'date',
        'fiscal_year_end' => 'date',
        'total_budget' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Entity::class, 'company_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_id');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function budgetActuals(): HasMany
    {
        return $this->hasMany(BudgetActual::class);
    }

    public function budgetAlerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class);
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getApprovedByAttribute(): ?int
    {
        return $this->approved_by_id;
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->save();
    }

    public function approve(\App\Models\User|int $approver): void
    {
        $this->status = 'approved';
        $this->approved_by_id = $approver instanceof \App\Models\User ? $approver->id : $approver;
        $this->approved_at = now();
        $this->save();
    }

    // ─── Financial helpers ────────────────────────────────────────────────────

    public function remainingBudget(): float
    {
        return round((float) $this->total_budget - (float) $this->total_spent, 2);
    }

    public function utilizationRate(): float
    {
        $total = (float) $this->total_budget;
        return $total > 0 ? round(((float) $this->total_spent / $total) * 100, 2) : 0.0;
    }

    public function variance(): float
    {
        return round((float) $this->total_budget - (float) $this->total_spent, 2);
    }

    public function isOverBudget(): bool
    {
        // Check via lines if total_spent is zero
        $spent = (float) $this->total_spent;
        if ($spent > 0) {
            return $spent > (float) $this->total_budget;
        }
        // Fall back to lines
        $expenseLines = $this->lines()->where(function ($q) {
            $q->whereHas('glAccount', fn ($q2) => $q2->where('type', 'expense'))
              ->orWhereHas('account', fn ($q2) => $q2->where('type', 'expense'));
        })->get();
        if ($expenseLines->isEmpty()) {
            // Any line with positive variance = over budget
            return $this->lines()->where('variance', '>', 0)->exists();
        }
        $totalVariance = $expenseLines->sum('variance');
        return $totalVariance > 0;
    }

    public function recompute(): void
    {
        $spent = (float) $this->lines()->sum('spent_amount');
        $this->total_spent = $spent;
        $this->save();
    }

    public function varianceSummary(): array
    {
        $revenueVariance = 0.0;
        $expenseVariance = 0.0;

        foreach ($this->lines()->with('account')->get() as $line) {
            $type = optional($line->account)->type ?? optional($line->glAccount)->account_type ?? 'expense';
            $v = (float) $line->variance;
            if ($type === 'revenue') {
                $revenueVariance += $v;
            } else {
                $expenseVariance += $v;
            }
        }

        $netVariance = $revenueVariance - $expenseVariance;
        $totalBudget = (float) $this->total_budget;
        $variancePct = $totalBudget > 0 ? round($netVariance / $totalBudget * 100, 2) : 0.0;

        return [
            'revenue_variance' => round($revenueVariance, 2),
            'expense_variance' => round($expenseVariance, 2),
            'net_variance' => round($netVariance, 2),
            'variance_pct' => $variancePct,
        ];
    }

    public function getTotalActual(): float
    {
        return (float) $this->budgetActuals()->sum('actual_amount');
    }

    public function getTotalVariance(): float
    {
        return $this->getTotalActual() - (float) $this->total_budget;
    }

    public function getVariancePercent(): float
    {
        return (float) $this->total_budget > 0 ? ($this->getTotalVariance() / (float) $this->total_budget) * 100 : 0;
    }
}
