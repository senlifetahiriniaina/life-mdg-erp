<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $id
 * @property int              $model_id
 * @property string           $status
 * @property string|null      $rmse
 * @property string|null      $mae
 * @property string|null      $mape
 * @property int|null         $training_duration_seconds
 * @property int              $records_processed
 * @property string|null      $error_message
 * @property \Carbon\Carbon   $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read ForecastModel $model
 */
class ModelRetrainingLog extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_model_retraining_logs';

    protected $fillable = [
        'model_id',
        'status',
        'rmse',
        'mae',
        'mape',
        'training_duration_seconds',
        'records_processed',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'rmse'       => 'decimal:4',
        'mae'        => 'decimal:4',
        'mape'       => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function complete(string $rmse, string $mae, string $mape): void
    {
        $duration = (int) now()->diffInSeconds($this->started_at);
        $this->update([
            'status'                    => 'success',
            'rmse'                      => $rmse,
            'mae'                       => $mae,
            'mape'                      => $mape,
            'training_duration_seconds' => $duration,
            'completed_at'              => now(),
        ]);
    }

    public function fail(string $errorMessage): void
    {
        $duration = (int) now()->diffInSeconds($this->started_at);
        $this->update([
            'status'                    => 'failed',
            'error_message'             => $errorMessage,
            'training_duration_seconds' => $duration,
            'completed_at'              => now(),
        ]);
    }

    public function getDurationMinutes(): float
    {
        return $this->training_duration_seconds / 60;
    }
}
