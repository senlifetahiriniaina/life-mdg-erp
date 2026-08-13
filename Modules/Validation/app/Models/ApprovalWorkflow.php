<?php

namespace Modules\Validation\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Validation\Database\Factories\ApprovalWorkflowFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $module_name
 * @property bool $is_active
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ApprovalWorkflow extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected static function newFactory(): ApprovalWorkflowFactory
    {
        return ApprovalWorkflowFactory::new();
    }

    protected $table = 'validation_approval_workflows';

    protected static string $auditModule = 'Validation';

    protected $fillable = [
        'name',
        'description',
        'module_name',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class, 'workflow_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'workflow_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRulesByOrder()
    {
        return $this->rules()->orderBy('rule_order')->get();
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
