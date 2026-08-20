<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Achats\Models\Supplier;

/**
 * Chantier 21 — one line of a CostingSheet: a material, an assembly/finish
 * accessory, or a value-added item (print/embroidery), matching the source
 * spreadsheets' section layout (Matière / Accessoire Montage / Accessoire
 * Finition / Valeur Ajoutée / Type Lavage). `line_total` is a snapshot
 * computed and persisted by CostingSheetService, not a live accessor —
 * material prices move, and an already-quoted sheet must stay stable.
 *
 * @property int $id
 * @property int $costing_sheet_id
 * @property string $section
 * @property string $designation
 * @property float $consumption_qty
 * @property float $unit_price
 * @property string $currency
 * @property float $customs_freight_percent
 * @property float|null $margin_percent
 * @property float $line_total
 */
class CostingSheetLine extends Model
{
    protected $table = 'inventory_costing_sheet_lines';

    protected $fillable = [
        'costing_sheet_id',
        'section',
        'designation',
        'product_template_id',
        'supplier_id',
        'consumption_qty',
        'unit',
        'unit_price',
        'currency',
        'customs_freight_percent',
        'margin_percent',
        'line_total',
        'sequence',
    ];

    protected $casts = [
        'consumption_qty' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'customs_freight_percent' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'line_total' => 'decimal:4',
        'sequence' => 'integer',
    ];

    public function costingSheet(): BelongsTo
    {
        return $this->belongsTo(CostingSheet::class, 'costing_sheet_id');
    }

    public function productTemplate(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
