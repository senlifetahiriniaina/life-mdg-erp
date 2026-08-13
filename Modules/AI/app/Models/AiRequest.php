<?php

declare(strict_types=1);

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traces every call made to the AI (Claude) API.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $module
 * @property string      $action
 * @property string      $model_used
 * @property int         $prompt_tokens
 * @property int         $completion_tokens
 * @property float       $cost_usd
 * @property int         $response_time_ms
 * @property string      $status           pending|success|error|cached
 * @property \Carbon\Carbon $created_at
 */
class AiRequest extends Model
{
    public $timestamps = false;

    protected $table = 'ai_requests';

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'model_used',
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',
        'response_time_ms',
        'status',
        'created_at',
    ];

    protected $casts = [
        'prompt_tokens'     => 'integer',
        'completion_tokens' => 'integer',
        'cost_usd'          => 'decimal:6',
        'response_time_ms'  => 'integer',
        'created_at'        => 'datetime',
    ];

    /**
     * The user who initiated this AI request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Total tokens (prompt + completion).
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }
}
