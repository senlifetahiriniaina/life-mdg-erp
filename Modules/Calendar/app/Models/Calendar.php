<?php

declare(strict_types=1);

namespace Modules\Calendar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property string $type  personal|shared|module
 * @property string $source local|google|outlook|apple
 * @property bool $is_primary
 * @property bool $is_visible
 * @property string|null $sync_token
 * @property string|null $external_calendar_id
 */
class Calendar extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'calendar_calendars';

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'color', 'type', 'source',
        'is_primary', 'is_visible', 'sync_token', 'external_calendar_id',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'is_visible'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'calendar_id');
    }
}
