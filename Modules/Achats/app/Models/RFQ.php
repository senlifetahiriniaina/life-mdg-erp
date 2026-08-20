<?php

namespace Modules\Achats\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Achats\Database\Factories\RFQFactory;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $rfq_number
 * @property string $status
 * @property string|null $description
 * @property Carbon $required_by_date
 * @property Carbon|null $issued_date
 * @property Carbon|null $deadline_date
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class RFQ extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected $table = 'achats_rfqs';

    protected static string $auditModule = 'Achats';

    protected static function newFactory(): RFQFactory
    {
        return RFQFactory::new();
    }

    protected $fillable = [
        'rfq_number',
        'status',
        'description',
        'required_by_date',
        'issued_date',
        'deadline_date',
        'created_by',
        // Chantier 19: this module had no company scoping at all.
        'company_id',
    ];

    protected $casts = [
        'required_by_date' => 'date',
        'issued_date' => 'date',
        'deadline_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RFQLine::class, 'rfq_id');
    }

    // Alias for factory()->has(RFQLine::factory()) pattern
    public function rFQLines(): HasMany
    {
        return $this->hasMany(RFQLine::class, 'rfq_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class, 'rfq_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'closed')->where('status', '!=', 'cancelled');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('deadline_date', '<', now()->toDateString());
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getLowestQuote(): ?SupplierQuote
    {
        return $this->quotes()
            ->where('status', 'submitted')
            ->orderBy('total_price')
            ->first();
    }

    public function getQuoteBySupplier(int $supplier_id): ?SupplierQuote
    {
        return $this->quotes()
            ->where('supplier_id', $supplier_id)
            ->first();
    }
}
