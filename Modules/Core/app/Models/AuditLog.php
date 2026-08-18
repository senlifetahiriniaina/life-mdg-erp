<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\AuditLogFactory;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $company_id
 * @property string|null $user_name
 * @property string|null $user_role
 * @property string $action
 * @property string|null $module
 * @property string|null $event_type
 * @property string|null $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'core_audit_logs';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'company_id',
        'user_name',
        'user_role',
        'action',
        'module',
        'event_type',
        'description',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isCreate(): bool
    {
        return $this->action === 'created';
    }

    public function isUpdate(): bool
    {
        return $this->action === 'updated';
    }

    public function isDelete(): bool
    {
        return $this->action === 'deleted';
    }

    public function hasValueChanges(): bool
    {
        return $this->old_values !== null || $this->new_values !== null;
    }

    public function getChangedFields(): array
    {
        if ($this->new_values === null) {
            return [];
        }

        $changed = [];
        foreach (array_keys($this->new_values) as $field) {
            $oldVal = $this->old_values[$field] ?? null;
            $newVal = $this->new_values[$field] ?? null;
            if ($oldVal !== $newVal) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
