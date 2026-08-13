<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $source_id
 * @property string               $status
 * @property string               $sync_type
 * @property int                  $records_attempted
 * @property int                  $records_synced
 * @property int                  $records_failed
 * @property int|null             $duration_seconds
 * @property string|null          $data_size_mb
 * @property string|null          $error_message
 * @property array<string, mixed>|null $error_log
 * @property \Carbon\Carbon       $started_at
 * @property \Carbon\Carbon|null  $completed_at
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read ExternalDataSource $source
 */
class SyncHistory extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_sync_history';

    protected $fillable = [
        'source_id',
        'status',
        'sync_type',
        'records_attempted',
        'records_synced',
        'records_failed',
        'duration_seconds',
        'data_size_mb',
        'error_message',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'error_log'     => 'array',
        'data_size_mb'  => 'decimal:2',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalDataSource::class, 'source_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function getSuccessRate(): float
    {
        if ($this->records_attempted === 0) {
            return 0.0;
        }
        return ($this->records_synced / $this->records_attempted) * 100;
    }

    public function getDurationMinutes(): float
    {
        return ($this->duration_seconds ?? 0) / 60;
    }

    public function complete(int $recordsSynced, int $recordsFailed, ?float $dataSizeMb = null): void
    {
        $duration = (int) now()->diffInSeconds($this->started_at);
        $status = $recordsFailed === 0 ? 'success' : 'partial';

        $this->update([
            'status'            => $status,
            'records_synced'    => $recordsSynced,
            'records_failed'    => $recordsFailed,
            'duration_seconds'  => $duration,
            'data_size_mb'      => $dataSizeMb,
            'completed_at'      => now(),
        ]);
    }

    public function fail(string $errorMessage, ?array $errorLog = null): void
    {
        $duration = (int) now()->diffInSeconds($this->started_at);
        $this->update([
            'status'            => 'failed',
            'error_message'     => $errorMessage,
            'error_log'         => $errorLog,
            'duration_seconds'  => $duration,
            'completed_at'      => now(),
        ]);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending'  => 'Pending',
            'running'  => 'Running',
            'success'  => 'Success',
            'failed'   => 'Failed',
            'partial'  => 'Partial',
            default    => ucfirst($this->status),
        };
    }
}
