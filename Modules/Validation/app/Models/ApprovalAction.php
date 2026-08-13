<?php

namespace Modules\Validation\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $request_id
 * @property int $approver_id
 * @property string $action
 * @property string|null $comment
 * @property Carbon|null $acted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ApprovalAction extends Model
{
    use HasFactory;
    protected $table = 'validation_approval_actions';

    protected $fillable = [
        'request_id',
        'approver_id',
        'action',
        'comment',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isApproved(): bool
    {
        return $this->action === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->action === 'rejected';
    }

    public function getDecision(): string
    {
        return $this->action;
    }
}
