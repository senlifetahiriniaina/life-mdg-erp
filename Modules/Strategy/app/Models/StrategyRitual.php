<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyRitual extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'cadence',
        'day_of_week',
        'day_of_month',
        'attendee_roles',
        'plan_id',
        'is_active',
    ];

    protected $casts = [
        'attendee_roles' => 'array',
        'is_active'      => 'boolean',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(StrategyRitualSession::class, 'ritual_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StrategyPlan::class, 'plan_id');
    }
}
