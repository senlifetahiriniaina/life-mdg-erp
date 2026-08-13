<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $agent_id
 * @property int    $template_id
 * @property string $preferred_variation
 * @property string $tone_preference
 * @property array  $frequent_modifications
 * @property float  $customization_score
 * @property int    $times_used
 * @property int    $times_modified
 * @property float  $avg_satisfaction_with_variant
 * @property bool   $has_custom_variant
 * @property int    $custom_variant_id
 * @property array  $learning_data
 * @property bool   $is_learning_enabled
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ResponseCustomization extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_response_customization';

    protected $fillable = [
        'agent_id',
        'template_id',
        'preferred_variation',
        'tone_preference',
        'frequent_modifications',
        'customization_score',
        'times_used',
        'times_modified',
        'avg_satisfaction_with_variant',
        'has_custom_variant',
        'custom_variant_id',
        'learning_data',
        'is_learning_enabled',
        'last_used_at',
    ];

    protected $casts = [
        'customization_score' => 'decimal:4',
        'avg_satisfaction_with_variant' => 'decimal:2',
        'has_custom_variant' => 'boolean',
        'is_learning_enabled' => 'boolean',
        'frequent_modifications' => 'json',
        'learning_data' => 'json',
        'last_used_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ResponseTemplate::class, 'template_id');
    }

    public function customVariant(): BelongsTo
    {
        return $this->belongsTo(AIResponseVariant::class, 'custom_variant_id');
    }

    public function isHighlyCustomized(): bool
    {
        return $this->customization_score >= 0.7;
    }

    public function getCustomizationRate(): float
    {
        if ($this->times_used === 0) {
            return 0;
        }

        return $this->times_modified / $this->times_used;
    }

    public function recordUsage(): void
    {
        $this->increment('times_used');
        $this->update(['last_used_at' => now()]);
    }

    public function recordModification(): void
    {
        $this->increment('times_modified');
        $this->update(['last_used_at' => now()]);
    }
}
