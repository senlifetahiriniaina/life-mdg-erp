<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                       $id
 * @property int                       $company_id
 * @property int                       $created_by
 * @property string                    $name
 * @property string|null               $description
 * @property string                    $metric_source
 * @property int                       $metric_source_id
 * @property string                    $status
 * @property bool                      $is_public
 * @property int                       $condition_count
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\User     $creator
 * @property-read \App\Models\Company  $company
 */
class AlertRule extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_alert_rules';

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'metric_source',
        'metric_source_id',
        'status',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(AlertCondition::class, 'rule_id')->orderBy('condition_order');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AlertRecipient::class, 'rule_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(AlertEscalation::class, 'rule_id')->orderBy('escalation_level');
    }

    public function dndSchedules(): HasMany
    {
        return $this->hasMany(DndSchedule::class, 'rule_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AlertHistory::class, 'rule_id')->latest();
    }

    public function deduplication(): HasMany
    {
        return $this->hasMany(AlertDeduplication::class, 'rule_id');
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function updateConditionCount(): void
    {
        $count = $this->conditions()->count();
        $this->update(['condition_count' => $count]);
    }
}
