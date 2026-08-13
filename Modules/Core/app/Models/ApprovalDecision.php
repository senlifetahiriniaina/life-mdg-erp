<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $instance_id
 * @property int $step_order
 * @property int|null $approver_id
 * @property string $decision
 * @property string|null $comment
 * @property Carbon $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApprovalDecision extends Model
{
    use HasFactory;
    protected $table = 'core_approval_decisions';

    protected $fillable = [
        'instance_id',
        'step_order',
        'approver_id',
        'decision',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'decided_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
