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
 * @property string $language
 * @property string $model_type
 * @property string $provider
 * @property string $model_identifier
 * @property float  $accuracy
 * @property int    $training_samples
 * @property \Illuminate\Support\Carbon|null $trained_at
 * @property \Illuminate\Support\Carbon|null $deployed_at
 * @property string $status
 * @property array  $hyperparameters
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SentimentModel extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_sentiment_models';

    protected $fillable = [
        'name',
        'language',
        'model_type',
        'provider',
        'model_identifier',
        'accuracy',
        'training_samples',
        'trained_at',
        'deployed_at',
        'status',
        'hyperparameters',
        'notes',
    ];

    protected $casts = [
        'accuracy' => 'decimal:4',
        'trained_at' => 'datetime',
        'deployed_at' => 'datetime',
        'hyperparameters' => 'json',
    ];

    public function sentimentScores(): HasMany
    {
        return $this->hasMany(SentimentScore::class, 'sentiment_model_id');
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

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }
}
