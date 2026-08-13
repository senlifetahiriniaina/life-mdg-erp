<?php

declare(strict_types=1);

namespace Modules\Calendar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $user_id
 * @property string $email
 * @property string|null $name
 * @property string $status  accepted|declined|tentative|needs-action
 * @property bool $is_organizer
 */
class CalendarAttendee extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'calendar_attendees';

    protected $fillable = [
        'event_id', 'user_id', 'email', 'name', 'status', 'is_organizer',
    ];

    protected $casts = [
        'is_organizer' => 'boolean',
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
