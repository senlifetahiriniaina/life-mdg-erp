<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\TaxEntryFactory;

/**
 * @property int $id
 * @property int $tax_rate_id
 * @property int|null $invoice_id
 * @property int|null $journal_entry_id
 * @property string $taxable_amount
 * @property string $tax_amount
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TaxRate|null $taxRate
 */
class TaxEntry extends Model
{
    use HasFactory;

    protected $table = 'acc_tax_entries';

    protected $fillable = [
        'tax_rate_id',
        'invoice_id',
        'journal_entry_id',
        'taxable_amount',
        'tax_amount',
        'period_start',
        'period_end',
        'type',
    ];

    protected $casts = [
        'taxable_amount' => 'encrypted:decimal:4',
        'tax_amount' => 'encrypted:decimal:4',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    protected static function newFactory(): TaxEntryFactory
    {
        return TaxEntryFactory::new();
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function isCollected(): bool
    {
        return $this->type === 'collected';
    }

    public function isPaid(): bool
    {
        return $this->type === 'paid';
    }

    public function netTaxLiability(): float
    {
        return (float) $this->taxable_amount * $this->taxRate->getEffectiveRate() / 100;
    }
}
