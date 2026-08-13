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
 * @property string $action
 * @property string|null $old_status
 * @property string|null $new_status
 * @property int $changed_by
 * @property Carbon $changed_at
 */
class ApprovalHistory extends Model
{
    use HasFactory;
    protected $table = 'validation_approval_history';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'old_status',
        'new_status',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function formatChange(): string
    {
        return "{$this->old_status} → {$this->new_status}";
    }
}
