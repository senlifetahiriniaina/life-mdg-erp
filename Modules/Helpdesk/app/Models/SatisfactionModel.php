<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property string $name
 * @property string $model_type
 * @property string $provider
 * @property string $model_identifier
 * @property float  $rmse
 * @property float  $mae
 * @property float  $r_squared
 * @property int    $training_samples
 * @property \Illuminate\Support\Carbon|null $trained_at
 * @property \Illuminate\Support\Carbon|null $deployed_at
 * @property string $status
 * @property array  $feature_importance
 * @property array  $hyperparameters
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SatisfactionModel extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_satisfaction_models';

    protected $fillable = [
        'name',
        'model_type',
        'provider',
        'model_identifier',
        'rmse',
        'mae',
        'r_squared',
        'training_samples',
        'trained_at',
        'deployed_at',
        'status',
        'feature_importance',
        'hyperparameters',
        'notes',
    ];

    protected $casts = [
        'rmse' => 'decimal:4',
        'mae' => 'decimal:4',
        'r_squared' => 'decimal:4',
        'trained_at' => 'datetime',
        'deployed_at' => 'datetime',
        'feature_importance' => 'json',
        'hyperparameters' => 'json',
    ];

    public function satisfactionPredictions(): HasMany
    {
        return $this->hasMany(SatisfactionPrediction::class, 'satisfaction_model_id');
    }

    public function isDeployed(): bool
    {
        return $this->status === 'deployed' && $this->deployed_at !== null;
    }

    public function deploy(): void
    {
        $this->update([
            'status' => 'deployed',
            'deployed_at' => now(),
        ]);
    }

    public function getAccuracy(): float
    {
        // R² score is a good measure of accuracy
        return $this->r_squared ?? 0;
    }
}
