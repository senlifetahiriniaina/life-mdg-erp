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
        'role',
        'approver_order',
        'backup_user_id',
        'backup_role',
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

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * The actual User(s) this row resolves to: the specific user_id if set,
     * otherwise every user currently holding `role`. A row is expected to
     * have exactly one of user_id/role set (enforced at write time, not by a
     * DB constraint — matches this codebase's existing convention).
     */
    public function resolvesToUsers(): \Illuminate\Support\Collection
    {
        if ($this->user_id) {
            return ($user = User::find($this->user_id)) ? collect([$user]) : collect();
        }

        if ($this->role) {
            return User::role($this->role)->get();
        }

        return collect();
    }

    /**
     * The backup User(s) for this row: backup_user_id if set, otherwise every
     * user holding backup_role. Never reads primary user_id/role as a backup.
     */
    public function resolvesToBackupUsers(): \Illuminate\Support\Collection
    {
        if ($this->backup_user_id) {
            return ($user = User::find($this->backup_user_id)) ? collect([$user]) : collect();
        }

        if ($this->backup_role) {
            return User::role($this->backup_role)->get();
        }

        return collect();
    }
}
