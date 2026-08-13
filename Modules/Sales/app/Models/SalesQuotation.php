<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $reference
 * @property int|null $contact_id
 * @property string $status
 * @property string $currency
 * @property float $total
 * @property \Carbon\Carbon|null $valid_until
 * @property string|null $notes
 * @property int|null $converted_to_order_id
 * @property int $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class SalesQuotation extends Model
{
    use AuditableActions, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'sales_quotations';

    protected string $auditModule = 'Sales';
    protected array $auditableFields = ['status', 'total', 'converted_to_order_id'];

    protected $fillable = [
        'tenant_id',
        'reference',
        'contact_id',
        'status',
        'currency',
        'total',
        'valid_until',
        'notes',
        'converted_to_order_id',
        'created_by',
    ];

    protected $casts = [
        'total'       => 'decimal:2',
        'valid_until' => 'date',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_order_id');
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

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'sent']);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'draft')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now()->toDateString());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isConvertible(): bool
    {
        return in_array($this->status, ['draft', 'sent', 'accepted'], true)
            && $this->converted_to_order_id === null;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
