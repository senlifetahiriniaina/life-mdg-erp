<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Logistics\Database\Factories\FreightInvoiceFactory;

class FreightInvoice extends Model
{
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected static string $auditModule = 'Logistics';

    protected static function newFactory(): FreightInvoiceFactory
    {
        return FreightInvoiceFactory::new();
    }

    protected $table = 'logistics_freight_invoices';

    protected $fillable = [
        'invoice_number',
        'shipment_id',
        'carrier_id',
        'type',
        'status',
        'quoted_amount',
        'invoiced_amount',
        'variance_amount',
        'currency',
        'invoice_date',
        'due_date',
        'paid_at',
        'carrier_invoice_ref',
        'dispute_reason',
        'notes',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'quoted_amount' => 'decimal:2',
        'invoiced_amount' => 'decimal:2',
        'variance_amount' => 'decimal:2',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
