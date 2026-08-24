<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\CallLogFactory;

/**
 * @property int $id
 * @property string|null $call_sid
 * @property int|null $contact_id
 * @property int|null $lead_id
 * @property int $user_id
 * @property string $direction
 * @property string $status
 * @property int|null $duration
 * @property string $phone_number
 * @property string|null $recording_url
 * @property string|null $notes
 * @property Carbon $called_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact|null $contact
 * @property-read Lead|null $lead
 * @property-read User $user
 */
class CallLog extends Model
{
    use HasFactory;

    protected static function newFactory(): CallLogFactory
    {
        return CallLogFactory::new();
    }

    protected $table = 'crm_call_logs';

    protected $fillable = [
        'tenant_id',
        'call_sid',
        'contact_id',
        'lead_id',
        'user_id',
        'direction',
        'status',
        // Chantier 38.3: the real crm_call_logs column is `duration` (confirmed via
        // Schema::getColumnListing()) — `duration_seconds` was never a real column, so every
        // mass-assignment of it was silently dropped. CallLogResource keeps exposing it under
        // the external `duration_seconds` JSON key for API-contract stability.
        'duration',
        'phone_number',
        'recording_url',
        'notes',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'duration' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
