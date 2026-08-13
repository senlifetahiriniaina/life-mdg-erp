<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $votable_type
 * @property int $votable_id
 * @property int $user_id
 * @property int $vote  1 or -1
 */
class ForumVote extends Model
{
    protected $table = 'helpdesk_forum_votes';

    protected $guarded = [];

    protected $casts = [
        'vote' => 'integer',
    ];

    public function votable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
