<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Traits\AuditableActions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\TaxRateFactory;
use Modules\Accounting\Traits\CalculatesTax;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $rate
 * @property string $type
 * @property string|null $country
 * @property bool $is_active
 * @property bool $is_compound
 * @property string|null $applies_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TaxEntry> $entries
 */
class TaxRate extends Model
{
    use AuditableActions, CalculatesTax, HasFactory;

    protected $table = 'acc_tax_rates';

    protected $auditableFields = ['rate', 'is_active', 'is_compound'];
    protected $auditModule = 'Accounting';

    protected $fillable = [
        'name',
        'code',
        'rate',
        'type',
        'country',
        'is_active',
        'is_compound',
        'applies_to',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_active' => 'boolean',
        'is_compound' => 'boolean',
    ];

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TaxEntry::class, 'tax_rate_id');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isCompound(): bool
    {
        return $this->is_compound === true;
    }

    protected function getTaxRate(): float
    {
        return (float) $this->rate;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function getEffectiveRate(): float
    {
        return (float) $this->rate;
    }
}
