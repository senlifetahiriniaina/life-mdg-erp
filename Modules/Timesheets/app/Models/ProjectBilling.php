<?php

declare(strict_types=1);

namespace Modules\Timesheets\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectBilling — billing entries for projects with OHADA revenue account.
 *
 * Africa First:
 *   OHADA account 7061 — Travaux et études facturés
 *   TVA at 18% (UEMOA) applied on amounts HT
 *   References: BILL-YYYY-NNNN (billing) and INV-YYYY-NNNN (invoice)
 *
 * @property int $id
 * @property int $project_id
 * @property string $reference         — BILL-YYYY-NNNN
 * @property string $billing_type      — milestone | percentage | time_material | fixed
 * @property float $amount             — HT in XOF
 * @property float $tva_amount         — TVA in XOF
 * @property float $total_ttc          — TTC in XOF
 * @property string $status            — draft | sent | paid | cancelled
 * @property string $ohada_account     — '7061'
 * @property string|null $description
 * @property string $billing_date
 * @property int|null $milestone_id
 * @property float|null $percentage
 * @property string|null $period_start
 * @property string|null $period_end
 * @property string|null $invoice_reference  — INV-YYYY-NNNN once generated
 *
 * Computed:
 * @property-read string $status_color  — CSS color class
 */
class ProjectBilling extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'ts_project_billing';

    protected $fillable = [
        'project_id', 'reference', 'billing_type', 'amount', 'tva_amount',
        'total_ttc', 'status', 'ohada_account', 'description', 'billing_date',
        'milestone_id', 'percentage', 'period_start', 'period_end',
        'invoice_reference',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'tva_amount'   => 'decimal:2',
        'total_ttc'    => 'decimal:2',
        'percentage'   => 'decimal:2',
        'billing_date' => 'date',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    // -------------------------------------------------------------------------
    // Accessors (Phase 49)
    // -------------------------------------------------------------------------

    /**
     * Status color for UI chips.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'gray',
            'sent'      => 'blue',
            'paid'      => 'green',
            'cancelled' => 'red',
            default     => 'gray',
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function project(): BelongsTo
    {
        // Projects module project
        return $this->belongsTo(\Modules\Projects\Models\Project::class, 'project_id');
    }
}
