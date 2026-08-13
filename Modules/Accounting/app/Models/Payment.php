<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\PaymentFactory;

/**
 * Invoice payment (maps to acc_invoice_payments).
 *
 * @property int|null $invoice_id
 * @property string $amount
 * @property string $status
 */
class Payment extends Model
{
    use HasFactory;

    protected $table = 'acc_invoice_payments';

    protected $fillable = [
        'invoice_id',
        'payment_date',
        'amount',
        'currency',
        'payment_method',
        'reference',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
