<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $role
 * @property bool $can_edit_tasks
 * @property bool $can_manage_members
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Project $project
 */
class ProjectTeamMember extends Model
{
    use HasFactory;
    protected $table = 'prj_team_members';

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'can_edit_tasks',
        'can_manage_members',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'can_edit_tasks' => 'boolean',
        'can_manage_members' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
