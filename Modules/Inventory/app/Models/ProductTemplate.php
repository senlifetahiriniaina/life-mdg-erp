<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\ProductTemplateFactory;

/**
 * Chantier 17 — a reusable product blueprint for the clothing/textile
 * domain (matières premières, accessoires, vêtements semi-finis, produits
 * finis). Picking a template in the product-creation UI pre-fills
 * category/unit/attributes; the associated Category carries the suggested
 * chart-of-accounts routing (see Category's default_*_account_code columns).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $family
 * @property int $category_id
 * @property int|null $unit_id
 * @property string|null $description
 * @property array|null $default_attributes
 * @property bool $is_active
 */
class ProductTemplate extends Model
{
    use HasFactory, RecordsActivity;

    protected static string $auditModule = 'Inventory';

    protected $table = 'inventory_product_templates';

    protected $fillable = [
        'code',
        'name',
        'family',
        'category_id',
        'unit_id',
        'description',
        'default_attributes',
        'is_active',
    ];

    protected $casts = [
        'default_attributes' => 'array',
        'is_active' => 'boolean',
    ];

    /** Product families this catalogue covers, in production order. */
    public const FAMILIES = [
        'matiere_premiere' => 'Matière première',
        'accessoire' => 'Accessoire',
        'semi_fini' => 'Vêtement semi-fini',
        'produit_fini' => 'Produit fini',
    ];

    protected static function newFactory()
    {
        return ProductTemplateFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFamily($query, string $family)
    {
        return $query->where('family', $family);
    }
}
