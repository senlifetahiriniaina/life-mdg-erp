<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\BI\Database\Factories\ScheduledReportFactory;

/**
 * @property int $id
 * @property string $name
 * @property int|null $report_id
 * @property string $schedule
 * @property int|null $day_of_week
 * @property int|null $day_of_month
 * @property string $format
 * @property array<string, mixed> $recipients
 * @property bool $is_active
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $next_send_at
 * @property int $send_count
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScheduledReport extends Model
{
    use HasFactory;

    protected $table = 'bi_scheduled_reports';

    protected $fillable = [
        'name',
        'report_id',
        'schedule',
        'day_of_week',
        'day_of_month',
        'format',
        'recipients',
        'is_active',
        'last_sent_at',
        'next_send_at',
        'send_count',
        'created_by',
    ];

    protected $casts = [
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime',
        'send_count' => 'integer',
    ];

    protected static function newFactory(): ScheduledReportFactory
    {
        return ScheduledReportFactory::new();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isDue(): bool
    {
        return $this->next_send_at !== null && $this->next_send_at->isPast();
    }

    public function computeNextSend(): Carbon
    {
        return match ($this->schedule) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            default => now()->addDay(),
        };
    }

    public function markSent(): void
    {
        $this->update([
            'last_sent_at' => now(),
            'next_send_at' => $this->computeNextSend(),
            'send_count' => $this->send_count + 1,
        ]);
    }
}
