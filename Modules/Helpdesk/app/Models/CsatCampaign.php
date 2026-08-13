<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Helpdesk\Database\Factories\CsatCampaignFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $trigger
 * @property int $delay_hours
 * @property string $question_text
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CsatCampaign extends Model
{
    use HasFactory;

    protected static function newFactory(): CsatCampaignFactory
    {
        return CsatCampaignFactory::new();
    }

    protected $table = 'helpdesk_csat_campaigns';

    protected $fillable = [
        'name',
        'trigger',
        'delay_hours',
        'question_text',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'delay_hours' => 'integer',
    ];

    public function surveys(): HasMany
    {
        return $this->hasMany(CsatSurvey::class, 'campaign_id');
    }
}
