<?php

namespace Modules\CRM\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $status
 * @property int $owner_id
 * @property int $target_count
 * @property int $enrolled_count
 * @property int $converted_count
 * @property float $conversion_rate
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property array|null $channels
 * @property array|null $segments
 * @property array|null $metadata
 */
class Campaign extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity, AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_campaigns';

    protected string $auditModule = 'CRM';
    protected array $auditableFields = ['status', 'name', 'enrolled_count', 'converted_count'];

    protected $fillable = [
        'name', 'description', 'type', 'status', 'owner_id',
        'target_count', 'enrolled_count', 'converted_count', 'conversion_rate',
        'start_date', 'end_date', 'channels', 'segments', 'metadata',
    ];

    protected $casts = [
        'conversion_rate' => 'decimal:2',
        'channels'        => 'array',
        'segments'        => 'array',
        'metadata'        => 'array',
        'start_date'      => 'datetime',
        'end_date'        => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CampaignStage::class, 'campaign_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CampaignEnrollment::class, 'campaign_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CampaignAction::class, 'campaign_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(CampaignAnalytic::class, 'campaign_id');
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->enrolled_count === 0) {
            return 0;
        }

        return ($this->converted_count / $this->enrolled_count) * 100;
    }
}
