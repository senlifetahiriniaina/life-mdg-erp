<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $module
 * @property string $resource_type
 * @property array<int, array<string, mixed>> $steps
 * @property array<int, array<string, mixed>> $transitions
 * @property bool $is_active
 * @property int|null $created_by
 */
class WorkflowDefinition extends Model
{
    use HasFactory;

    protected $table = 'core_workflow_definitions';

    protected $fillable = [
        'name',
        'module',
        'resource_type',
        'steps',
        'transitions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'steps' => 'array',
        'transitions' => 'array',
        'is_active' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function states(): HasMany
    {
        return $this->hasMany(WorkflowState::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return the step object whose key matches $key, or null.
     *
     * @return array<string, mixed>|null
     */
    public function getStepByKey(string $key): ?array
    {
        foreach ($this->steps as $step) {
            if (($step['key'] ?? null) === $key) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Return all transitions whose 'from' matches $fromKey.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTransitionsFrom(string $fromKey): array
    {
        return array_values(
            array_filter(
                $this->transitions,
                fn (array $t) => ($t['from'] ?? null) === $fromKey
            )
        );
    }

    /**
     * Return transitions from $fromKey that the given roles are allowed to use.
     *
     * @param  array<string>  $userRoles
     * @return array<int, array<string, mixed>>
     */
    public function getAllowedTransitions(string $fromKey, array $userRoles): array
    {
        return array_values(
            array_filter(
                $this->getTransitionsFrom($fromKey),
                function (array $transition) use ($userRoles): bool {
                    $allowed = $transition['allowed_roles'] ?? [];

                    // Empty allowed_roles means everyone can take this transition.
                    if (empty($allowed)) {
                        return true;
                    }

                    return (bool) array_intersect($userRoles, $allowed);
                }
            )
        );
    }
}
