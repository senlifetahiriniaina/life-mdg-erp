<?php

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Projects\Database\Factories\ProjectFactory;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $budget
 * @property string|null $currency
 * @property string|null $color
 * @property bool $is_billable
 */
class Project extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Projects';

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected $table = 'prj_projects';

    protected $fillable = [
        'owner_id', 'name', 'code', 'description', 'status',
        'start_date', 'end_date', 'budget', 'currency', 'color', 'is_billable',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'is_billable' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'prj_members', 'project_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class, 'project_id');
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class, 'project_id');
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(AutomationRule::class, 'project_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'project_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ProjectTeamMember::class, 'project_id');
    }

    public function billing(): HasOne
    {
        return $this->hasOne(ProjectBilling::class, 'project_id');
    }
}
