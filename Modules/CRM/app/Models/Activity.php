<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $due_at
 * @property Carbon|null $done_at
 * @property string|null $subject_type
 * @property int|null $subject_id
 */
class Activity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'user_id', 'company_id', 'type', 'title', 'description',
        'status', 'due_at', 'done_at', 'subject_type', 'subject_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
