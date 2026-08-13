<?php

namespace Modules\Validation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $hierarchy_id
 * @property int $level_order
 * @property string $title
 * @property int $approver_count
 * @property bool $delegation_allowed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class HierarchyLevel extends Model
{
    use HasFactory;
    protected $table = 'validation_hierarchy_levels';

    protected $fillable = [
        'hierarchy_id',
        'level_order',
        'title',
        'approver_count',
        'delegation_allowed',
    ];

    protected $casts = [
        'delegation_allowed' => 'boolean',
    ];

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(ApprovalHierarchy::class, 'hierarchy_id');
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(LevelApprover::class, 'hierarchy_level_id');
    }

    public function activeApprovers(): HasMany
    {
        return $this->approvers()->where('is_active', true);
    }
}
