<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Database\Factories\AutomationRuleFactory;

/**
 * @property int $id
 * @property int|null $project_id
 * @property string $name
 * @property string $trigger
 * @property array<string,mixed> $conditions
 * @property array<string,mixed> $actions
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AutomationRule extends Model
{
    use HasFactory;

    protected $table = 'prj_automation_rules';

    protected static function newFactory(): AutomationRuleFactory
    {
        return AutomationRuleFactory::new();
    }

    protected $fillable = [
        'project_id',
        'name',
        'trigger',
        'conditions',
        'actions',
        'active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
