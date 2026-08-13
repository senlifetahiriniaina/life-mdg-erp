<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $priority
 * @property float $first_response_hours
 * @property float $resolution_hours
 * @property bool $business_hours_only
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HelpdeskSlaPolicy extends Model
{
    use HasFactory;
    protected $table = 'hd_helpdesk_sla_policies';

    protected $fillable = [
        'name',
        'priority',
        'first_response_hours',
        'resolution_hours',
        'business_hours_only',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'first_response_hours' => 'float',
        'resolution_hours' => 'float',
        'business_hours_only' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function escalationRules(): HasMany
    {
        return $this->hasMany(EscalationRule::class, 'sla_policy_id');
    }
}
