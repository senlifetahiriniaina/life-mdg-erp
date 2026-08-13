<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Helpdesk\Database\Factories\SlaPolicyFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $priority
 * @property int $response_time_minutes
 * @property int $resolution_time_minutes
 * @property bool $business_hours_only
 * @property array<string,mixed>|null $business_hours
 * @property bool $escalation_enabled
 * @property int|null $escalation_after_minutes
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SlaPolicy extends Model
{
    use HasFactory;

    protected static function newFactory(): SlaPolicyFactory
    {
        return SlaPolicyFactory::new();
    }

    protected $table = 'hd_sla_policies';

    protected $fillable = [
        'name',
        'description',
        'priority',
        'response_time_minutes',
        'resolution_time_minutes',
        'business_hours_only',
        'business_hours',
        'escalation_enabled',
        'escalation_after_minutes',
        'is_active',
    ];

    protected $casts = [
        'business_hours_only' => 'boolean',
        'business_hours' => 'array',
        'escalation_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function responseDeadline(Carbon $startedAt): Carbon
    {
        return $startedAt->copy()->addMinutes($this->response_time_minutes);
    }

    public function resolutionDeadline(Carbon $startedAt): Carbon
    {
        return $startedAt->copy()->addMinutes($this->resolution_time_minutes);
    }

    public function isBreached(Carbon $deadline): bool
    {
        return $deadline->isPast();
    }

    public function minutesUntilBreach(Carbon $deadline): int
    {
        return (int) now()->diffInMinutes($deadline, false);
    }
}
