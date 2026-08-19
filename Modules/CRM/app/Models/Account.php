<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property int|null $owner_id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $type
 * @property string|null $industry
 * @property string|null $website
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $employee_count
 * @property string|null $annual_revenue
 * @property string|null $currency
 * @property string|null $billing_address
 * @property string|null $billing_city
 * @property string|null $billing_country
 * @property string|null $description
 * @property array<string,mixed>|null $custom_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Account extends Model
{
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected $table = 'crm_accounts';

    protected static string $auditModule = 'CRM';

    protected $fillable = [
        'owner_id', 'company_id', 'name', 'type', 'industry', 'website', 'phone', 'email',
        'employee_count', 'annual_revenue', 'currency', 'billing_address',
        'billing_city', 'billing_country', 'description', 'custom_fields',
        'status', 'revenue',
    ];

    protected $casts = [
        'annual_revenue' => 'decimal:2',
        'custom_fields' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'account_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'account_id');
    }
}
