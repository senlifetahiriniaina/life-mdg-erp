<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of an AutomationFlow at a given publish point.
 *
 * @property int         $id
 * @property int         $flow_id
 * @property int         $version_number
 * @property string|null $label
 * @property int|null    $created_by
 * @property array       $nodes_snapshot
 * @property array       $connections_snapshot
 * @property array|null  $flow_meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FlowVersion extends Model
{
    protected $table = 'flow_versions';

    protected $fillable = [
        'flow_id',
        'version_number',
        'label',
        'created_by',
        'nodes_snapshot',
        'connections_snapshot',
        'flow_meta',
    ];

    protected $casts = [
        'nodes_snapshot'       => 'array',
        'connections_snapshot' => 'array',
        'flow_meta'            => 'array',
        'version_number'       => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }
}
