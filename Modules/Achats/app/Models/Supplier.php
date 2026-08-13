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
use Modules\Achats\Database\Factories\SupplierFactory;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $tax_number
 * @property string $currency
 * @property string $payment_terms
 * @property int $lead_time_days
 * @property bool $is_active
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Supplier extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected $table = 'achats_suppliers';

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    protected static string $auditModule = 'Achats';

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'tax_number',
        'currency',
        'payment_terms',
        'lead_time_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function getDefaultPaymentTerms(): string
    {
        return $this->payment_terms;
    }

    public function getAverageLeadTime(): int
    {
        return $this->lead_time_days;
    }
}
