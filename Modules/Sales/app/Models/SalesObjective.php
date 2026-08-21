<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chantier 26 (volet B) — une cible de chiffre d'affaires proposée (par
 * l'application, avec ou sans rationale IA) ou validée, pour un périmètre
 * (`scope`) et une période donnés. Voir la migration de création pour le
 * détail du modèle de données.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $scope
 * @property int|null $scope_ref_id
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 * @property float $target_amount
 * @property string $currency
 * @property string|null $proposal_label
 * @property string|null $basis
 * @property float|null $growth_rate_percent
 * @property string $status
 * @property string $source
 * @property int|null $created_by
 * @property int|null $validated_by
 * @property \Carbon\Carbon|null $validated_at
 */
class SalesObjective extends Model
{
    use HasFactory;

    public const SCOPES = ['global', 'rep', 'client', 'category'];

    protected $table = 'sales_objectives';

    protected $fillable = [
        'tenant_id',
        'scope',
        'scope_ref_id',
        'period_start',
        'period_end',
        'target_amount',
        'currency',
        'proposal_label',
        'basis',
        'growth_rate_percent',
        'status',
        'source',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'period_start'         => 'date',
        'period_end'           => 'date',
        'target_amount'        => 'decimal:2',
        'growth_rate_percent'  => 'decimal:2',
        'validated_at'         => 'datetime',
    ];

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSiblings(Builder $query, self $objective): Builder
    {
        return $query
            ->where('tenant_id', $objective->tenant_id)
            ->where('scope', $objective->scope)
            ->where('scope_ref_id', $objective->scope_ref_id)
            ->whereDate('period_start', $objective->period_start)
            ->whereDate('period_end', $objective->period_end)
            ->where('id', '!=', $objective->id);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
