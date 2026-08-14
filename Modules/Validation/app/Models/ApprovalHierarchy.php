<?php

namespace Modules\Validation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $company_id
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ApprovalHierarchy extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected $table = 'validation_approval_hierarchies';

    protected static string $auditModule = 'Validation';

    protected $fillable = [
        'name',
        'description',
        'company_id',
        'module_name',
        'is_active',
        'escalation_role',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function levels(): HasMany
    {
        return $this->hasMany(HierarchyLevel::class, 'hierarchy_id')->orderBy('level_order');
    }

    public function allApprovers(): HasMany
    {
        return $this->hasManyThrough(
            LevelApprover::class,
            HierarchyLevel::class,
            'hierarchy_id',
            'hierarchy_level_id'
        );
    }

    public function getApproversByLevel(string $level)
    {
        return $this->levels()
            ->where('title', $level)
            ->first()
            ?->approvers()
            ->active()
            ->orderBy('approver_order')
            ->get();
    }

    public function getBackupApprovers()
    {
        return $this->allApprovers()
            ->where('backup_user_id', '!=', null)
            ->get();
    }
}
