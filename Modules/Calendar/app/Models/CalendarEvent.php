<?php

declare(strict_types=1);

namespace Modules\Calendar\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $calendar_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property bool $all_day
 * @property string|null $location
 * @property string|null $url
 * @property string|null $recurrence_rule
 * @property array|null $recurrence_exception_dates
 * @property string $status confirmed|tentative|cancelled
 * @property string $visibility public|private
 * @property string $source local|google|outlook|apple|module
 * @property string|null $external_event_id
 * @property string|null $external_etag
 * @property string|null $module_type
 * @property int|null $module_id
 * @property string|null $color
 * @property int|null $created_by
 */
class CalendarEvent extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'calendar_events';

    protected $fillable = [
        'tenant_id', 'calendar_id', 'title', 'description',
        'start_at', 'end_at', 'all_day', 'location', 'url',
        'recurrence_rule', 'recurrence_exception_dates',
        'status', 'visibility', 'source',
        'external_event_id', 'external_etag',
        'module_type', 'module_id',
        'color', 'created_by',
    ];

    protected $casts = [
        'all_day'                      => 'boolean',
        'start_at'                     => 'datetime',
        'end_at'                       => 'datetime',
        'recurrence_exception_dates'   => 'array',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarAttendee::class, 'event_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarReminder::class, 'event_id');
    }

    /**
     * Polymorphic relationship to any module record
     * (Task, Leave, Objective, Ticket, etc.)
     */
    public function module(): MorphTo
    {
        return $this->morphTo('module', 'module_type', 'module_id');
    }
}
