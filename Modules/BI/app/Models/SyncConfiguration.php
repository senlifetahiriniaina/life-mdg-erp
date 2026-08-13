<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                   $id
 * @property int                   $source_id
 * @property string                $sync_type
 * @property string                $frequency
 * @property string|null           $scheduled_time
 * @property string|null           $day_of_week
 * @property int|null              $day_of_month
 * @property bool                  $is_active
 * @property int                   $batch_size
 * @property int                   $max_retries
 * @property int                   $retry_delay_minutes
 * @property array<string, mixed>|null $filter_criteria
 * @property \Carbon\Carbon        $created_at
 * @property \Carbon\Carbon        $updated_at
 * @property-read ExternalDataSource $source
 */
class SyncConfiguration extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_sync_configurations';

    protected $fillable = [
        'source_id',
        'sync_type',
        'frequency',
        'scheduled_time',
        'day_of_week',
        'day_of_month',
        'is_active',
        'batch_size',
        'max_retries',
        'retry_delay_minutes',
        'filter_criteria',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'filter_criteria' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalDataSource::class, 'source_id');
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getFrequencyLabel(): string
    {
        return match ($this->frequency) {
            'manual'   => 'Manual',
            'hourly'   => 'Hourly',
            'daily'    => 'Daily',
            'weekly'   => 'Weekly',
            'monthly'  => 'Monthly',
            default    => ucfirst($this->frequency),
        };
    }

    public function getSyncTypeLabel(): string
    {
        return match ($this->sync_type) {
            'full'        => 'Full Sync',
            'incremental' => 'Incremental',
            'delta'       => 'Delta',
            default       => ucfirst($this->sync_type),
        };
    }

    public function getFilterCriteria(): array
    {
        return $this->filter_criteria ?? [];
    }
}
