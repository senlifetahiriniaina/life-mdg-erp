<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyObjectiveLink extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'strategy_objective_id',
        'linkable_type',
        'linkable_id',
        'contribution_value',
        'unit_type',
    ];

    protected $casts = [
        'contribution_value' => 'float',
    ];

    /**
     * Get the strategy objective this link belongs to.
     */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(StrategyObjective::class, 'strategy_objective_id');
    }

    /**
     * Get the linked resource (polymorphic resolution).
     * @return object|null
     */
    public function getLinkedResource()
    {
        $class = $this->getResourceClass();
        if (!class_exists($class)) {
            return null;
        }
        return $class::find($this->linkable_id);
    }

    /**
     * Resolve the full class path from the linkable_type string.
     * Example: 'Accounting/Invoice' → 'Modules\Accounting\Models\Invoice'
     */
    public function getResourceClass(): string
    {
        [$module, $model] = explode('/', $this->linkable_type);
        return "Modules\\$module\\Models\\$model";
    }

    /**
     * Get a display label for the linked resource type.
     */
    public function getResourceTypeLabel(): string
    {
        return str_replace('/', ' / ', $this->linkable_type);
    }
}
