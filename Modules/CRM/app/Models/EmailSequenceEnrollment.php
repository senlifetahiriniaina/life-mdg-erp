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
 * @property int|null $contact_id
 * @property int|null $lead_id
 * @property int $current_step
 * @property string $status
 * @property Carbon $enrolled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmailSequence $sequence
 * @property-read Contact|null $contact
 * @property-read Lead|null $lead
 */
class EmailSequenceEnrollment extends Model
{
    use HasFactory;
    protected $table = 'crm_email_sequence_enrollments';

    protected $fillable = [
        'sequence_id',
        'contact_id',
        'lead_id',
        'current_step',
        'status',
        'enrolled_at',
        'completed_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_step' => 'integer',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
