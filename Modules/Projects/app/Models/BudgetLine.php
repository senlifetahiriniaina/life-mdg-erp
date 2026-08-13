<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BudgetLine — CAPEX/OPEX budget tracking per project, mapped to OHADA accounts.
 *
 * Africa First:
 *   CAPEX → OHADA Classe 2 (Immobilisations)
 *   OPEX  → OHADA Classe 6 (Charges)
 *
 * @property int $id
 * @property int $project_id
 * @property string $description
 * @property string $category  — 'capex' | 'opex'
 * @property string $ohada_account
 * @property float $estimated_amount
 * @property float $actual_amount
 * @property string|null $phase
 * @property string|null $notes
 *
 * Computed:
 * @property-read float $variance
 * @property-read float $variance_pct
 */
class BudgetLine extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'prj_budget_lines';

    protected $fillable = [
        'project_id', 'description', 'category', 'ohada_account',
        'estimated_amount', 'actual_amount', 'phase', 'notes',
    ];

    protected $casts = [
        'estimated_amount' => 'decimal:2',
        'actual_amount'    => 'decimal:2',
    ];

    // -------------------------------------------------------------------------
    // Accessors (Phase 49)
    // -------------------------------------------------------------------------

    /**
     * Variance = actual − estimated (positive = over budget).
     */
    public function getVarianceAttribute(): float
    {
        return round((float) $this->actual_amount - (float) $this->estimated_amount, 2);
    }

    /**
     * Variance % relative to estimated (positive = over budget).
     */
    public function getVariancePctAttribute(): float
    {
        $estimated = (float) $this->estimated_amount;
        if ($estimated === 0.0) {
            return 0.0;
        }
        return round(($this->getVarianceAttribute() / $estimated) * 100, 2);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
