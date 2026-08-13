<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sequence_id
 * @property int $step_order
 * @property int $delay_days
 * @property string $subject
 * @property string $body_html
 * @property string|null $body_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmailSequence $sequence
 */
class EmailSequenceStep extends Model
{
    use HasFactory;
    protected $table = 'crm_email_sequence_steps';

    protected $fillable = [
        'sequence_id',
        'step_order',
        'delay_days',
        'subject',
        'body_html',
        'body_text',
    ];

    protected $casts = [
        'delay_days' => 'integer',
        'step_order' => 'integer',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }
}
