<?php

namespace Modules\Validation\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hierarchy_level_id
 * @property int $user_id
 * @property int $approver_order
 * @property int|null $backup_user_id
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LevelApprover extends Model
{
    use HasFactory;
    protected $table = 'validation_level_approvers';

    protected $fillable = [
        'hierarchy_level_id',
        'user_id',
        'approver_order',
        'backup_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(HierarchyLevel::class, 'hierarchy_level_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function backupUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'backup_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
