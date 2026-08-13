<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property float $rate
 * @property string $country_code
 * @property Carbon $applies_from
 * @property Carbon|null $applies_to
 * @property string $type
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VatRate extends Model
{
    use HasFactory;
    protected $table = 'acc_vat_rates';

    protected $fillable = [
        'name',
        'rate',
        'country_code',
        'applies_from',
        'applies_to',
        'type',
        'is_default',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'applies_from' => 'date',
        'applies_to' => 'date',
        'is_default' => 'boolean',
    ];
}
