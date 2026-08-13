<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\SequenceStepFactory;

/**
 * @property int $id
 * @property int $sequence_id
 * @property int $order
 * @property int $delay_days
 * @property string $subject
 * @property string $body
 * @property string|null $from_name
 * @property string|null $from_email
 */
class SequenceStep extends Model
{
    use HasFactory;

    protected static function newFactory(): SequenceStepFactory
    {
        return SequenceStepFactory::new();
    }

    protected $table = 'crm_sequence_steps';

    protected $fillable = [
        'sequence_id',
        'order',
        'delay_days',
        'subject',
        'body',
        'from_name',
        'from_email',
        'step_order',
        'delay_hours',
        'body_html',
    ];

    protected $casts = [
        'delay_days' => 'integer',
        'step_order' => 'integer',
        'delay_hours' => 'integer',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }
}
