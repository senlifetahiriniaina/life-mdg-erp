<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\CostingSheetFactory;

/**
 * Chantier 21 — nomenclature de coût (BOM devis chiffré) : reproduit
 * numériquement le chiffrage matière+accessoires+main-d'œuvre+frais fixes
 * que l'équipe avant-vente calcule aujourd'hui à la main dans un tableur,
 * pour un article/quantité donnés. Les totaux sont recalculés et
 * persistés (snapshot) par CostingSheetService à chaque modification de
 * ligne — pas des accesseurs calculés à la volée, puisque les prix
 * matière bougent et qu'un devis déjà envoyé au client doit rester figé.
 *
 * @property int $id
 * @property string $reference
 * @property string $name
 * @property int|null $product_template_id
 * @property int|null $opportunity_id
 * @property string $status
 * @property int $version
 * @property string $base_currency
 * @property float $total_cost_price
 * @property float $suggested_selling_price
 */
class CostingSheet extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Inventory';

    protected $table = 'inventory_costing_sheets';

    protected $fillable = [
        'reference',
        'name',
        'product_template_id',
        'opportunity_id',
        'gender',
        'size_range',
        'season',
        'quantity',
        'base_currency',
        'status',
        'version',
        'parent_id',
        'production_minutes',
        'minute_cost',
        'labor_cost',
        'fixed_cost_coefficient',
        'washing_cost',
        'target_margin_percent',
        'total_material_cost',
        'total_assembly_cost',
        'total_finishing_cost',
        'total_value_added_cost',
        'total_cost_price',
        'suggested_selling_price',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'version' => 'integer',
        'production_minutes' => 'decimal:2',
        'minute_cost' => 'decimal:4',
        'labor_cost' => 'decimal:4',
        'fixed_cost_coefficient' => 'decimal:4',
        'washing_cost' => 'decimal:4',
        'target_margin_percent' => 'decimal:2',
        'total_material_cost' => 'decimal:4',
        'total_assembly_cost' => 'decimal:4',
        'total_finishing_cost' => 'decimal:4',
        'total_value_added_cost' => 'decimal:4',
        'total_cost_price' => 'decimal:4',
        'suggested_selling_price' => 'decimal:4',
    ];

    /** Sections a costing line can belong to, matching the source spreadsheets' layout. */
    public const SECTIONS = [
        'matiere' => 'Matière',
        'accessoire_montage' => 'Accessoire de montage',
        'accessoire_finition' => 'Accessoire de finition',
        'valeur_ajoutee' => 'Valeur ajoutée',
        'lavage' => 'Type lavage',
    ];

    public const STATUSES = ['draft', 'quoted', 'approved', 'archived'];

    protected static function newFactory()
    {
        return CostingSheetFactory::new();
    }

    public function productTemplate(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CostingSheetLine::class, 'costing_sheet_id')->orderBy('sequence');
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
