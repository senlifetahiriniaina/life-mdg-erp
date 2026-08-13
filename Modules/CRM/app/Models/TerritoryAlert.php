<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerritoryAlert extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_territory_alerts';

    protected $fillable = [
        'territory_id', 'alert_type', 'severity', 'title', 'message',
        'status', 'assigned_to', 'triggered_at', 'resolved_at', 'metadata',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
        'metadata'     => 'json',
    ];

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function getIsCriticalAttribute(): bool
    {
        return $this->severity === 'critical';
    }
}
