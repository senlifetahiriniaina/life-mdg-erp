<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $period_type
 * @property int $period_year
 * @property int $period_number
 * @property string $status
 * @property float $total_sales
 * @property float $total_purchases
 * @property float $vat_collected
 * @property float $vat_deductible
 * @property float $vat_due
 * @property Carbon|null $submitted_at
 * @property string|null $reference
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VatDeclaration extends Model
{
    use HasFactory;
    protected $table = 'acc_vat_declarations';

    protected $fillable = [
        'period_type',
        'period_year',
        'period_number',
        'status',
        'total_sales',
        'total_purchases',
        'vat_collected',
        'vat_deductible',
        'vat_due',
        'submitted_at',
        'reference',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_number' => 'integer',
        'total_sales' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'vat_collected' => 'decimal:2',
        'vat_deductible' => 'decimal:2',
        'vat_due' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];
}
