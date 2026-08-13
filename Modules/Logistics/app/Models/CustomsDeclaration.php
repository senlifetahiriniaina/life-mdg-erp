<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Logistics\Database\Factories\CustomsDeclarationFactory;

class CustomsDeclaration extends Model
{
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected static string $auditModule = 'Logistics';

    protected static function newFactory(): CustomsDeclarationFactory
    {
        return CustomsDeclarationFactory::new();
    }

    protected $table = 'logistics_customs_declarations';

    protected $fillable = [
        'reference',
        'shipment_id',
        'type',
        'status',
        'country_export',
        'country_import',
        'incoterm',
        'total_declared_value',
        'declared_value',
        'currency',
        'total_duties',
        'total_taxes',
        'customs_broker',
        'mrn_number',
        'submitted_at',
        'cleared_at',
        'rejection_reason',
        'notes',
        'created_by',
        'hs_code',
        'item_description',
        'quantity',
        'country_of_origin',
        'declaration_number',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'cleared_at' => 'datetime',
        'total_declared_value' => 'decimal:2',
        'total_duties' => 'decimal:2',
        'total_taxes' => 'decimal:2',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
