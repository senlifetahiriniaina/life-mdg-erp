<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\SourcingBenchmarkFactory;

/**
 * Chantier 17 — one observed price for a product/material at an external
 * sourcing source (a named China/Europe textile supplier, or a manually
 * entered "autre"). A log that accumulates over time as the app is used —
 * NOT a live scrape (see SourcingBenchmarkService's docblock).
 *
 * @property int $id
 * @property int|null $product_id
 * @property int|null $product_template_id
 * @property string|null $material_label
 * @property string $source
 * @property string|null $source_name_other
 * @property string|null $source_url
 * @property float $unit_price
 * @property string $currency
 * @property string|null $unit
 * @property float|null $quantity_reference
 * @property \Carbon\Carbon $observed_at
 * @property string|null $notes
 */
class SourcingBenchmark extends Model
{
    use HasFactory, RecordsActivity;

    protected static string $auditModule = 'Inventory';

    protected $table = 'inventory_sourcing_benchmarks';

    protected $fillable = [
        'product_id',
        'product_template_id',
        'material_label',
        'source',
        'source_name_other',
        'source_url',
        'unit_price',
        'currency',
        'unit',
        'quantity_reference',
        'observed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'quantity_reference' => 'decimal:4',
        'observed_at' => 'date',
    ];

    /**
     * Real, named China/Europe textile sourcing sites the user asked to
     * benchmark against, plus a free-text fallback for anything else.
     */
    public const SOURCES = [
        'xm_textiles' => 'XM Textiles',
        'klopman' => 'Klopman',
        'carrington_textiles' => 'Carrington Textiles',
        'marina_textil' => 'Marina Textil',
        'tencate_protective_fabrics' => 'TenCate Protective Fabrics',
        'autre' => 'Autre',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productTemplate(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    protected static function newFactory()
    {
        return SourcingBenchmarkFactory::new();
    }

    public function sourceLabel(): string
    {
        if ($this->source === 'autre' && $this->source_name_other) {
            return $this->source_name_other;
        }

        return self::SOURCES[$this->source] ?? $this->source;
    }
}
