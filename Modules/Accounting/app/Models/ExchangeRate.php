<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\ExchangeRateFactory;

/**
 * @property int $id
 * @property string $base_currency
 * @property string $target_currency
 * @property string $rate
 * @property string $source
 * @property Carbon $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExchangeRate extends Model
{
    use HasFactory;

    protected static function newFactory(): ExchangeRateFactory
    {
        return ExchangeRateFactory::new();
    }

    protected $table = 'acc_exchange_rates';

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'source',
        'date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rate' => 'decimal:6',
        'date' => 'date',
    ];
}
