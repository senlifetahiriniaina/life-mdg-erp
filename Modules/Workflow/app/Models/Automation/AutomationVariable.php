<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flow_id
 * @property string $name
 * @property string $value_type  string|number|boolean|json
 * @property string|null $default_value
 * @property string|null $current_value
 * @property string|null $description
 */
class AutomationVariable extends Model
{
    use HasFactory;
    protected $table = 'automation_variables';

    protected $fillable = [
        'flow_id',
        'name',
        'value_type',
        'default_value',
        'current_value',
        'description',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /**
     * Returns the typed current value (falls back to default).
     */
    public function getTypedValue(): mixed
    {
        $raw = $this->current_value ?? $this->default_value;

        return match ($this->value_type) {
            'number'  => is_numeric($raw) ? (float) $raw : 0,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode((string) $raw, true),
            default   => (string) ($raw ?? ''),
        };
    }

    public function setValue(mixed $value): void
    {
        $this->current_value = match ($this->value_type) {
            'json'    => is_string($value) ? $value : json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default   => (string) $value,
        };
        $this->save();
    }
}
