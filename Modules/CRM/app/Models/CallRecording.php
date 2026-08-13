<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $call_id
 * @property string|null $recording_url
 * @property int|null $duration_seconds
 * @property string|null $transcript_text
 * @property array|null $ai_summary
 * @property string $status
 * @property int $company_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CallLog $callLog
 */
class CallRecording extends Model
{
    public const STATUS_RECORDING  = 'recording';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';

    protected $table = 'crm_call_recordings';

    protected $fillable = [
        'call_id',
        'recording_url',
        'duration_seconds',
        'transcript_text',
        'ai_summary',
        'status',
        'company_id',
    ];

    protected $casts = [
        'ai_summary'       => 'array',
        'duration_seconds' => 'integer',
    ];

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class, 'call_id');
    }
}
