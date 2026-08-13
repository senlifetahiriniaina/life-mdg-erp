<?php

declare(strict_types=1);

namespace Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IntegrationSyncLog
 *
 * Audit record of every sync operation triggered on an Integration.
 *
 * @property int         $id
 * @property int         $integration_id
 * @property string      $direction       in | out | both
 * @property string      $status          running | success | error
 * @property int         $records_synced
 * @property array|null  $errors
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class IntegrationSyncLog extends Model
{
    public $timestamps = false;

    protected $table = 'integration_sync_logs';

    protected $fillable = [
        'integration_id',
        'direction',
        'status',
        'records_synced',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'errors'         => 'array',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'records_synced' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Duration in seconds, or null if still running. */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }
}
