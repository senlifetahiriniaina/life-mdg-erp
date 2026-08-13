<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\SupplierFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $address
 * @property string|null $country
 * @property string $currency
 * @property string $payment_terms
 * @property int $lead_time_days
 * @property string|null $notes
 * @property bool $is_active
 */
class Supplier extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Inventory';

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    protected $table = 'inventory_suppliers';

    protected $fillable = [
        'name', 'code', 'email', 'phone', 'website', 'address',
        'country', 'currency', 'payment_terms', 'lead_time_days', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'inventory_product_suppliers')
            ->withPivot('supplier_sku', 'unit_cost', 'min_order_qty', 'lead_time_days', 'is_preferred')
            ->withTimestamps();
    }
}
