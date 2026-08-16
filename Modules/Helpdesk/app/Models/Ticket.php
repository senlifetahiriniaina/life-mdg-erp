<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Traits\RecordsActivity;
use Modules\Helpdesk\Database\Factories\TicketFactory;

/**
 * @property int $id
 * @property int|null $team_id
 * @property int|null $assignee_id
 * @property int|null $reporter_id
 * @property int|null $contact_id
 * @property int|null $sla_id
 * @property string $subject
 * @property string|null $description
 * @property string $channel
 * @property string $priority
 * @property string $status
 * @property string|null $type
 * @property string|null $source_ref
 * @property int|null $satisfaction_score
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $sla_due_at
 * @property bool $sla_breached
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Ticket extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Helpdesk';

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'HD-' . strtoupper(uniqid());
            }
        });

        static::created(function (Ticket $ticket) {
            if ($ticket->sla_due_at !== null) {
                return;
            }

            $policy = $ticket->sla_id
                ? SlaPolicy::find($ticket->sla_id)
                : SlaPolicy::where('is_default', true)->first();

            if ($policy) {
                app(\Modules\Helpdesk\Services\SlaService::class)->apply($ticket, $policy);
            }
        });
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }

    protected $table = 'hd_tickets';

    protected $attributes = [
        'status'      => 'open',
        'priority'    => 'medium',
        'channel'     => 'web',
        'sla_breached' => false,
    ];

    protected $fillable = [
        'ticket_number',
        'team_id',
        'assignee_id',
        'reporter_id',
        'customer_id',
        'contact_id',
        'sla_id',
        'subject',
        'description',
        'channel',
        'priority',
        'status',
        'type',
        'source_ref',
        'satisfaction_score',
        'first_response_at',
        'resolved_at',
        'sla_due_at',
        'sla_breached',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached' => 'boolean',
        'satisfaction_score' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * The record this ticket was raised from, in any other module
     * (e.g. an Accounting invoice, a CRM contact, an Inventory product).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
