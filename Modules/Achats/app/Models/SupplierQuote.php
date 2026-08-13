<?php

namespace Modules\Achats\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Achats\Database\Factories\SupplierQuoteFactory;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property int $rfq_id
 * @property int $supplier_id
 * @property string $quote_number
 * @property string $unit_price
 * @property string $total_price
 * @property int $delivery_days
 * @property string|null $terms
 * @property Carbon $validity_date
 * @property string $status
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SupplierQuote extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected $table = 'achats_supplier_quotes';

    protected static string $auditModule = 'Achats';

    protected static function newFactory(): SupplierQuoteFactory
    {
        return SupplierQuoteFactory::new();
    }

    protected $fillable = [
        'rfq_id',
        'supplier_id',
        'quote_number',
        'unit_price',
        'total_price',
        'delivery_days',
        'terms',
        'validity_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
        'validity_date' => 'date',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class, 'rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calculateTotal(): float
    {
        return (float) $this->total_price;
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function accept(): void
    {
        $this->update(['status' => 'accepted']);
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }
}
