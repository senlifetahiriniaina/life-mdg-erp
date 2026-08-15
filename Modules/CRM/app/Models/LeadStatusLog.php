<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int $opportunity_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $updated_by
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class LeadStatusLog extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'crm_lead_status_logs';

    protected $fillable = [
        'opportunity_id',
        'from_status',
        'to_status',
        'updated_by',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
