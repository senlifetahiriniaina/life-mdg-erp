<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsItem extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_customs_items';

    protected $fillable = [
        'declaration_id',
        'product_id',
        'description',
        'hs_code',
        'qty',
        'unit',
        'unit_value',
        'total_value',
        'country_of_origin',
        'weight_kg',
    ];

    protected $casts = [
        'qty'         => 'decimal:3',
        'unit_value'  => 'decimal:2',
        'total_value' => 'decimal:2',
        'weight_kg'   => 'decimal:3',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class, 'declaration_id');
    }

    public function hsCode(): BelongsTo
    {
        return $this->belongsTo(HsCode::class, 'hs_code', 'code');
    }
}
