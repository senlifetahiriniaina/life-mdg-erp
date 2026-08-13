<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\CurrencyGainLossFactory;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property string $original_amount
 * @property string $original_currency
 * @property string $converted_amount
 * @property string $base_currency
 * @property string $gain_loss
 * @property bool $realized
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice|null $invoice
 */
class CurrencyGainLoss extends Model
{
    use HasFactory;

    protected static function newFactory(): CurrencyGainLossFactory
    {
        return CurrencyGainLossFactory::new();
    }

    protected $table = 'acc_currency_gains_losses';

    protected $fillable = [
        'invoice_id',
        'original_amount',
        'original_currency',
        'converted_amount',
        'base_currency',
        'gain_loss',
        'realized',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'original_amount' => 'decimal:2',
        'converted_amount' => 'decimal:2',
        'gain_loss' => 'decimal:2',
        'realized' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
