<?php

declare(strict_types=1);

namespace Modules\Calendar\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property int $minutes_before
 * @property string $method  email|push|popup
 * @property Carbon|null $sent_at
 */
class CalendarReminder extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'calendar_reminders';

    protected $fillable = [
        'event_id', 'user_id', 'minutes_before', 'method', 'is_sent', 'sent_at',
    ];

    protected $casts = [
        'sent_at'        => 'datetime',
        'minutes_before' => 'integer',
        'is_sent'        => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
