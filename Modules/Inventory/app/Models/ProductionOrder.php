<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Achats\Models\Supplier;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\ProductionOrderFactory;

/**
 * Chantier 23 (volet C de la feuille de route Chantier 21) — commande de
 * production simplifiée. Suit un article/quantité depuis une fiche de
 * chiffrage approuvée (CostingSheet) et/ou une commande de vente (Sales),
 * à travers un pipeline de statuts couvrant la sous-traitance de
 * production, jusqu'à la livraison. Délibérément pas un moteur MRP/BOM :
 * ni gamme de fabrication, ni nomenclature multi-niveaux, ni poste de
 * charge — Manufacturing est hors périmètre de cette extraction Life MDG.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $costing_sheet_id
 * @property int|null $sales_order_id
 * @property int|null $subcontractor_supplier_id
 * @property int $quantity
 * @property string $status
 */
class ProductionOrder extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Inventory';

    protected $table = 'inventory_production_orders';

    protected $fillable = [
        'reference',
        'costing_sheet_id',
        'sales_order_id',
        'subcontractor_supplier_id',
        'quantity',
        'status',
        'started_at',
        'expected_delivery_at',
        'delivered_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'started_at' => 'date',
        'expected_delivery_at' => 'date',
        'delivered_at' => 'date',
    ];

    /**
     * Pipeline linéaire — chaque statut ne peut avancer que vers le
     * suivant, ou basculer sur 'cancelled' depuis n'importe quel statut
     * non terminal. Validé par ProductionOrderService::transition().
     */
    public const STATUSES = [
        'draft',
        'materials_ready',
        'in_subcontracting',
        'quality_check',
        'ready_for_delivery',
        'delivered',
        'cancelled',
    ];

    protected static function newFactory()
    {
        return ProductionOrderFactory::new();
    }

    public function costingSheet(): BelongsTo
    {
        return $this->belongsTo(CostingSheet::class, 'costing_sheet_id');
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'subcontractor_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
