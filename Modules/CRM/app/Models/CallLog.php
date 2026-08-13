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
 * @property int|null $contact_id
 * @property int|null $lead_id
 * @property int $user_id
 * @property string $direction
 * @property string $status
 * @property int|null $duration_seconds
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
        'contact_id',
        'lead_id',
        'user_id',
        'direction',
        'status',
        'duration_seconds',
        'phone_number',
        'recording_url',
        'notes',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'duration_seconds' => 'integer',
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
