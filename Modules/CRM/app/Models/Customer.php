<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property string $status
 * @property string $tier
 * @property float $lifetime_value
 * @property int|null $created_by
 */
class Customer extends Model
{
    use HasFactory;

    protected $table = 'crm_customers';

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'status', 'tier',
        'lifetime_value', 'created_by',
    ];

    protected $casts = [
        'lifetime_value' => 'decimal:2',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
