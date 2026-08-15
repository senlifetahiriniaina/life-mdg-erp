<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Generic company/organization foundation used as a `belongsTo` target by
 * cross-cutting modules (Security, Analytics, BI) and by Accounting's
 * customer-facing models — distinct from Modules\Accounting\Models\Company
 * (the OHADA intercompany/consolidation entity) and from
 * Modules\Setup\Models\CompanyProfile (the tenant's own onboarding profile).
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string $currency
 * @property string $timezone
 * @property bool $is_active
 */
class Company extends Model
{
    use HasFactory;

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'currency',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
