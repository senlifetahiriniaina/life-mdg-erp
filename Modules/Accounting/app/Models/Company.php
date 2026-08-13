<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\CompanyFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int|null $parent_company_id
 * @property string $company_type
 * @property string $ownership_percentage
 * @property string $currency
 * @property int $fiscal_year_start_month
 * @property bool $is_active
 * @property int|null $elimination_account_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $parent
 * @property-read Collection<int, Company> $subsidiaries
 */
class Company extends Model
{
    use HasFactory;

    protected $table = 'acc_companies';

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'parent_company_id',
        'company_type',
        'ownership_percentage',
        'currency',
        'fiscal_year_start_month',
        'is_active',
        'elimination_account_id',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'fiscal_year_start_month' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    /** @return HasMany<Company, self> */
    public function subsidiaries(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_company_id');
    }

    public function intercompanyFrom(): HasMany
    {
        return $this->hasMany(IntercompanyTransaction::class, 'from_company_id');
    }

    public function intercompanyTo(): HasMany
    {
        return $this->hasMany(IntercompanyTransaction::class, 'to_company_id');
    }

    public function isParent(): bool
    {
        return $this->company_type === 'parent';
    }

    public function minorityInterest(): float
    {
        return (100 - (float) $this->ownership_percentage) / 100;
    }

    /**
     * @return Collection<int, Company>
     */
    public function allSubsidiaries(): Collection
    {
        /** @var Collection<int, Company> $result */
        $result = new Collection;
        $result->push($this);

        foreach ($this->subsidiaries as $subsidiary) {
            $result = $result->merge($subsidiary->allSubsidiaries());
        }

        return $result;
    }
}
