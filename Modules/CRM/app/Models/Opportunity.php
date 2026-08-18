<?php

namespace Modules\CRM\Models;

use App\Models\User;
use App\Traits\AuditableActions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\CRM\Database\Factories\OpportunityFactory;

/**
 * @property int $id
 * @property int|null $pipeline_id
 * @property int|null $account_id
 * @property int|null $contact_id
 * @property int|null $owner_id
 * @property int|null $territory_id
 * @property string $name
 * @property string|null $stage
 * @property int|null $probability
 * @property float|string $amount
 * @property string $currency
 * @property Carbon|null $expected_close_date
 * @property string $status
 * @property string|null $description
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OpportunityScore|null $score
 */
class Opportunity extends Model
{
    use AuditableActions, HasFactory, RecordsActivity, SoftDeletes;

    protected static function newFactory(): OpportunityFactory
    {
        return OpportunityFactory::new();
    }

    protected $table = 'crm_opportunities';

    protected string $auditModule = 'CRM';

    protected array $auditableFields = ['status', 'stage', 'probability', 'amount', 'closed_at'];

    protected $fillable = [
        'tenant_id', 'pipeline_id', 'account_id', 'contact_id', 'owner_id', 'territory_id', 'name',
        'stage', 'probability', 'amount', 'currency', 'expected_close_date',
        'title', 'lost_reason',
        'status', 'description', 'custom_fields', 'closed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'custom_fields' => 'array',
        'expected_close_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function score(): HasOne
    {
        return $this->hasOne(OpportunityScore::class, 'opportunity_id');
    }

    /** @return HasMany<EngagementSignal, $this> */
    public function engagementSignals(): HasMany
    {
        return $this->hasMany(EngagementSignal::class, 'opportunity_id');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }
}
