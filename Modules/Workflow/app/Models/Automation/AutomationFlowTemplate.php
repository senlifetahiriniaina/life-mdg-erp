<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property string $icon
 * @property array<string,mixed> $flow_definition  full nodes + connections JSON
 * @property bool $is_builtin
 */
class AutomationFlowTemplate extends Model
{
    use HasFactory;
    protected $table = 'automation_templates';

    protected $fillable = [
        'name',
        'description',
        'category',
        'icon',
        'flow_definition',
        'is_builtin',
    ];

    protected $casts = [
        'flow_definition' => 'array',
        'is_builtin'      => 'boolean',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeBuiltin(Builder $query): Builder
    {
        return $query->where('is_builtin', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /**
     * Instantiate a new AutomationFlow from this template for a given tenant.
     */
    public function instantiateForTenant(int $tenantId, int $createdBy): AutomationFlow
    {
        $def = $this->flow_definition;

        $flow = AutomationFlow::create([
            'tenant_id'      => $tenantId,
            'name'           => $def['name'] ?? $this->name,
            'description'    => $def['description'] ?? $this->description,
            'icon'           => $def['icon'] ?? $this->icon,
            'color'          => $def['color'] ?? '#6366F1',
            'is_active'      => false,
            'trigger_type'   => $def['trigger_type'] ?? 'manual',
            'trigger_config' => $def['trigger_config'] ?? null,
            'tags'           => $def['tags'] ?? [],
            'created_by'     => $createdBy,
        ]);

        // Create nodes
        $nodeIdMap = [];
        foreach ($def['nodes'] ?? [] as $nodeDef) {
            $node = AutomationNode::create([
                'flow_id'        => $flow->id,
                'node_type'      => $nodeDef['node_type'],
                'node_key'       => $nodeDef['node_key'],
                'label'          => $nodeDef['label'],
                'position_x'     => $nodeDef['position_x'] ?? 0,
                'position_y'     => $nodeDef['position_y'] ?? 0,
                'config'         => $nodeDef['config'] ?? null,
                'input_schema'   => $nodeDef['input_schema'] ?? null,
                'output_schema'  => $nodeDef['output_schema'] ?? null,
                'error_handling' => $nodeDef['error_handling'] ?? 'stop',
            ]);
            $nodeIdMap[$nodeDef['_template_id']] = $node->id;
        }

        // Create connections (remapping template node IDs → real IDs)
        foreach ($def['connections'] ?? [] as $connDef) {
            AutomationConnection::create([
                'flow_id'        => $flow->id,
                'source_node_id' => $nodeIdMap[$connDef['source_template_id']] ?? 0,
                'target_node_id' => $nodeIdMap[$connDef['target_template_id']] ?? 0,
                'condition_type' => $connDef['condition_type'] ?? 'always',
                'condition_expr' => $connDef['condition_expr'] ?? null,
            ]);
        }

        // Create variables
        foreach ($def['variables'] ?? [] as $varDef) {
            AutomationVariable::create([
                'flow_id'       => $flow->id,
                'name'          => $varDef['name'],
                'value_type'    => $varDef['value_type'] ?? 'string',
                'default_value' => $varDef['default_value'] ?? null,
                'description'   => $varDef['description'] ?? null,
            ]);
        }

        return $flow;
    }
}
