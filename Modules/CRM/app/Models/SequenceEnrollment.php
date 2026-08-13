<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\SequenceEnrollmentFactory;

/**
 * @property int $id
 * @property int $sequence_id
 * @property int $contact_id
 * @property int $current_step
 * @property string $status
 * @property Carbon $enrolled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $next_send_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EmailSequence $sequence
 */
class SequenceEnrollment extends Model
{
    use HasFactory;

    protected static function newFactory(): SequenceEnrollmentFactory
    {
        return SequenceEnrollmentFactory::new();
    }

    protected $table = 'crm_sequence_enrollments';

    protected $fillable = [
        'sequence_id',
        'contact_id',
        'current_step',
        'status',
        'enrolled_at',
        'completed_at',
        'next_send_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_send_at' => 'datetime',
        'current_step' => 'integer',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function advance(): void
    {
        $this->increment('current_step');
        $this->refresh();

        $nextStep = $this->sequence->steps()->where('order', $this->current_step)->first();

        if ($nextStep) {
            $this->update(['next_send_at' => now()->addDays($nextStep->delay_days)]);
        } else {
            $this->complete();
        }
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'next_send_at' => null,
        ]);
    }

    public function unsubscribe(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'next_send_at' => null,
        ]);
    }
}
