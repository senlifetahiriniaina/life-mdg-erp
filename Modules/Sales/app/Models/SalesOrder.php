<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Helpdesk\Traits\HelpdeskLinkable;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $reference
 * @property int|null $contact_id
 * @property int|null $account_id
 * @property int|null $opportunity_id
 * @property string $status
 * @property string $currency
 * @property float $subtotal
 * @property float $discount_amount
 * @property float $tax_amount
 * @property float $total
 * @property string|null $notes
 * @property array<string,mixed>|null $shipping_address
 * @property \Carbon\Carbon|null $expected_delivery_date
 * @property \Carbon\Carbon|null $confirmed_at
 * @property \Carbon\Carbon|null $cancelled_at
 * @property int $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class SalesOrder extends Model
{
    use AuditableActions, HelpdeskLinkable, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'sales_orders';

    protected string $auditModule = 'Sales';
    protected array $auditableFields = ['status', 'total', 'confirmed_at', 'cancelled_at'];

    protected $fillable = [
        'tenant_id',
        'reference',
        'contact_id',
        'account_id',
        'opportunity_id',
        'status',
        'currency',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'notes',
        'shipping_address',
        'expected_delivery_date',
        'confirmed_at',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'subtotal'              => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'total'                 => 'decimal:2',
        'shipping_address'      => 'array',
        'expected_delivery_date' => 'date',
        'confirmed_at'          => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled', 'returned']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'confirmed'], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'confirmed', 'processing'], true);
    }
}
