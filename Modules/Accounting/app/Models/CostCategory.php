<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Tenant-configurable cost category labels.
 *
 * The four canonical codes (CAPEX/OPEX/FINEX/RISKEX) are fixed; only the
 * human-readable label, description, and color are customisable per tenant.
 * This lets OHADA tenants display "Charges HAO" instead of "RISKEX".
 */
class CostCategory extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'cost_categories';

    protected $fillable = [
        'tenant_id',
        'code',
        'label',
        'description',
        'color',
        'is_active',
        'ohada_account_class',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Default category definitions used when seeding or as fallback labels.
     */
    public static function defaults(): array
    {
        return [
            [
                'code'               => 'CAPEX',
                'label'              => 'Immobilisations (CAPEX)',
                'description'        => 'Équipements, outillages, licences, amortissements d\'actifs fixes.',
                'color'              => '#3b82f6', // blue
                'ohada_account_class'=> '2',
            ],
            [
                'code'               => 'OPEX',
                'label'              => 'Charges d\'exploitation (OPEX)',
                'description'        => 'Main-d\'œuvre, énergie, consommables, logistique, frais généraux.',
                'color'              => '#22c55e', // green
                'ohada_account_class'=> '6',
            ],
            [
                'code'               => 'FINEX',
                'label'              => 'Charges financières (FINEX)',
                'description'        => 'Intérêts, frais bancaires, pertes de change, coûts d\'affacturage.',
                'color'              => '#f97316', // orange
                'ohada_account_class'=> '67',
            ],
            [
                'code'               => 'RISKEX',
                'label'              => 'Provisions / Risques (RISKEX)',
                'description'        => 'Provisions pour défauts, garanties, assurances, Charges HAO (OHADA).',
                'color'              => '#ef4444', // red
                'ohada_account_class'=> '69',
            ],
        ];
    }
}
