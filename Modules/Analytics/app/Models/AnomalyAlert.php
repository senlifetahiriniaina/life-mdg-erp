<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AnomalyAlert extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'anomaly_alerts';

    protected $fillable = [
        'detected_anomaly_id',
        'company_id',
        'user_id',
        'alert_type',
        'alert_status',
        'sent_at',
        'read_at',
        'acknowledged_at',
        'escalation_notes',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function detectedAnomaly(): BelongsTo
    {
        return $this->belongsTo(DetectedAnomaly::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
