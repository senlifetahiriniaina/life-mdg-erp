<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Database\Factories\EmailSequenceFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string $trigger_type
 * @property array<string,mixed>|null $trigger_config
 * @property int|null $created_by
 */
class EmailSequence extends Model
{
    use HasFactory;

    protected static function newFactory(): EmailSequenceFactory
    {
        return EmailSequenceFactory::new();
    }

    protected $table = 'crm_email_sequences';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'trigger_type',
        'trigger_config',
        'created_by',
        // Legacy fields kept for backward compatibility
        'trigger',
        'trigger_conditions',
        'is_active',
        'enabled',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'trigger_conditions' => 'array',
        'is_active' => 'boolean',
        'enabled' => 'boolean',
    ];

    // New relations using new table/models
    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class, 'sequence_id')->orderBy('order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class, 'sequence_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Status helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    // Enrollment counts
    public function enrollmentCount(): int
    {
        return $this->enrollments()->count();
    }

    public function activeEnrollmentCount(): int
    {
        return $this->enrollments()->where('status', 'active')->count();
    }
}
