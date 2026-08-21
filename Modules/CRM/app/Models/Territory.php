<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Database\Factories\TerritoryFactory;

/**
 * @property int $id
 * @property int|null $parent_territory_id
 * @property int $assigned_to
 * @property int|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $type
 * @property string|null $description
 * @property string|null $region
 * @property float|string $sales_target
 * @property string|null $quota_amount
 * @property string $currency
 * @property bool $is_active
 * @property array<int,array<string,mixed>>|null $rules
 * @property Carbon|null $year_start_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Territory extends Model
{
    use HasFactory;

    protected $table = 'crm_territories';

    protected $fillable = [
        'company_id',
        'parent_territory_id',
        'assigned_to',
        'owner_id',
        'name',
        'code',
        'type',
        'description',
        'region',
        'sales_target',
        'quota_amount',
        'currency',
        'is_active',
        'rules',
        'year_start_date',
    ];

    protected $casts = [
        'sales_target' => 'decimal:2',
        'quota_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'rules' => 'array',
        'year_start_date' => 'date',
    ];

    protected static function newFactory(): TerritoryFactory
    {
        return TerritoryFactory::new();
    }

    /**
     * Parent territory relation (for hierarchical territories).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'parent_territory_id');
    }

    /**
     * Child territories (subdivisions of this territory).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Territory::class, 'parent_territory_id');
    }

    /**
     * User assigned to this territory.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Opportunities in this territory.
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'territory_id');
    }

    /**
     * Year-to-date revenue: sum of closed_won opportunities >= year_start_date.
     */
    public function ytdRevenue(): float
    {
        $startDate = $this->year_start_date ?? now()->startOfYear();

        return (float) $this->opportunities()
            ->where('status', 'closed_won')
            ->whereDate('closed_at', '>=', $startDate)
            ->sum('amount');
    }

    /**
     * Quota attainment: ytd_revenue / sales_target * 100.
     */
    public function quotaAttainment(): float
    {
        if ($this->sales_target == 0) {
            return 0.0;
        }

        return ($this->ytdRevenue() / (float) $this->sales_target) * 100;
    }

    /**
     * Forecasted revenue: sum of open opportunities weighted by win_probability.
     * Includes proposal, negotiation, and closed_won stages.
     */
    public function forecastedRevenue(): float
    {
        $startDate = $this->year_start_date ?? now()->startOfYear();

        $opportunities = $this->opportunities()
            ->whereIn('status', ['open', 'closed_won'])
            ->whereIn('stage', ['proposal', 'negotiation', 'closed_won'])
            ->whereDate('updated_at', '>=', $startDate)
            ->with('score')
            ->get();

        $forecastedAmount = 0.0;

        foreach ($opportunities as $opportunity) {
            $winProbability = $opportunity->score?->win_probability ?? ($opportunity->probability / 100);
            $forecastedAmount += (float) $opportunity->amount * (float) $winProbability;
        }

        return $forecastedAmount;
    }

    /**
     * Quota forecast: forecasted_revenue / sales_target * 100.
     */
    public function quotaForecast(): float
    {
        if ($this->sales_target == 0) {
            return 0.0;
        }

        return ($this->forecastedRevenue() / (float) $this->sales_target) * 100;
    }

    /**
     * Open opportunities in this territory (not closed_lost).
     */
    public function opportunitiesInPipeline(): Collection
    {
        return $this->opportunities()
            ->where('status', '!=', 'closed_lost')
            ->get();
    }
}
