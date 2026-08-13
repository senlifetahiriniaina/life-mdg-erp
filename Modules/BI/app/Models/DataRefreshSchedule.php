<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                       $id
 * @property int                       $source_id
 * @property string                    $refresh_type
 * @property string|null               $frequency
 * @property int                       $retry_count
 * @property int                       $max_concurrent_syncs
 * @property bool                      $skip_weekends
 * @property array<int>|null           $excluded_dates
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property-read ExternalDataSource   $source
 */
class DataRefreshSchedule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_data_refresh_schedules';

    protected $fillable = [
        'source_id',
        'refresh_type',
        'frequency',
        'retry_count',
        'max_concurrent_syncs',
        'skip_weekends',
        'excluded_dates',
    ];

    protected $casts = [
        'skip_weekends'   => 'boolean',
        'excluded_dates'  => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalDataSource::class, 'source_id');
    }

    public function getRefreshTypeLabel(): string
    {
        return match ($this->refresh_type) {
            'on_demand' => 'On Demand',
            'scheduled' => 'Scheduled',
            'real_time' => 'Real-time',
            default     => ucfirst(str_replace('_', ' ', $this->refresh_type)),
        };
    }

    public function getFrequencyLabel(): string
    {
        if ($this->frequency === null) {
            return 'N/A';
        }

        return match ($this->frequency) {
            'hourly'  => 'Hourly',
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
            default   => ucfirst($this->frequency),
        };
    }

    public function shouldSkipToday(): bool
    {
        if ($this->skip_weekends && in_array(now()->dayOfWeek, [0, 6], true)) {
            return true;
        }

        if ($this->excluded_dates !== null) {
            $today = now()->format('Y-m-d');
            return in_array($today, $this->excluded_dates, true);
        }

        return false;
    }

    public function getExcludedDates(): array
    {
        return $this->excluded_dates ?? [];
    }

    public function addExcludedDate(string $date): void
    {
        $excluded = $this->getExcludedDates();
        if (! in_array($date, $excluded, true)) {
            $excluded[] = $date;
            $this->update(['excluded_dates' => $excluded]);
        }
    }

    public function removeExcludedDate(string $date): void
    {
        $excluded = $this->getExcludedDates();
        $excluded = array_filter($excluded, fn($d) => $d !== $date);
        $this->update(['excluded_dates' => array_values($excluded)]);
    }
}
