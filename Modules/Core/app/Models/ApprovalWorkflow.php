<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $module
 * @property string $resource_type
 * @property array<int,array<string,mixed>> $steps
 * @property bool $is_active
 * @property bool $allow_parallel
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApprovalWorkflow extends Model
{
    use HasFactory;
    protected $table = 'core_approval_workflows';

    protected $fillable = [
        'name',
        'description',
        'module',
        'resource_type',
        'steps',
        'is_active',
        'allow_parallel',
        'created_by',
    ];

    protected $casts = [
        'steps' => 'array',
        'is_active' => 'boolean',
        'allow_parallel' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ApprovalInstance::class, 'workflow_id');
    }
}
