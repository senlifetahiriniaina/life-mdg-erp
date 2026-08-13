<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Models\Automation\AutomationConnection;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\Automation\AutomationNode;
use Modules\Workflow\Models\Automation\FlowVersion;
use RuntimeException;

/**
 * Flow Versioning Service — snapshot, list, and restore AutomationFlow versions.
 *
 * Guarantees:
 *   - createVersion() always increments version_number and sets is_published=true.
 *   - rollback() restores nodes + connections from a snapshot atomically.
 *   - History is immutable — existing snapshots are never overwritten.
 */
class FlowVersionService
{
    // ── Public API ───────────────────────────────────────────────────────────────

    /**
     * Snapshot the current state of a flow as a new immutable version.
     *
     * @param  int         $flowId
     * @param  string|null $label     Optional human-readable tag.
     * @param  int|null    $createdBy User id that published the version.
     * @throws RuntimeException when the flow is not found.
     */
    public function createVersion(int $flowId, ?string $label = null, ?int $createdBy = null): FlowVersion
    {
        $flow = $this->findFlowOrFail($flowId);

        return DB::transaction(function () use ($flow, $label, $createdBy): FlowVersion {
            // Determine next version_number
            $nextVersionNumber = ($flow->version_number ?? 0) + 1;

            // Snapshot nodes
            $nodes = AutomationNode::where('flow_id', $flow->id)->get();
            $nodesSnapshot = $nodes->map(fn (AutomationNode $n) => $n->toArray())->toArray();

            // Snapshot connections
            $connections = AutomationConnection::where('flow_id', $flow->id)->get();
            $connectionsSnapshot = $connections->map(fn (AutomationConnection $c) => $c->toArray())->toArray();

            // Snapshot flow-level meta
            $flowMeta = $flow->only([
                'name', 'description', 'trigger_type', 'trigger_config',
                'icon', 'color', 'tags',
            ]);

            // Persist the snapshot
            $version = FlowVersion::create([
                'flow_id'              => $flow->id,
                'version_number'       => $nextVersionNumber,
                'label'                => $label,
                'created_by'           => $createdBy,
                'nodes_snapshot'       => $nodesSnapshot,
                'connections_snapshot' => $connectionsSnapshot,
                'flow_meta'            => $flowMeta,
            ]);

            // Update the flow header
            $flow->update([
                'version_number'    => $nextVersionNumber,
                'is_published'      => true,
                'parent_version_id' => $version->id,
            ]);

            return $version;
        });
    }

    /**
     * List all versions for a flow, newest first.
     *
     * @return Collection<int, FlowVersion>
     */
    public function listVersions(int $flowId): Collection
    {
        $this->findFlowOrFail($flowId);

        return FlowVersion::where('flow_id', $flowId)
            ->orderByDesc('version_number')
            ->get();
    }

    /**
     * Restore a flow to the state captured in the given version snapshot.
     *
     * Nodes and connections of the live flow are replaced atomically.
     * A new snapshot is automatically created after restoration so the
     * rollback event is recorded in the version history.
     *
     * @param  int $flowId
     * @param  int $versionId  PK of the FlowVersion to restore from.
     * @throws RuntimeException when flow or version is not found, or the version
     *                          does not belong to the given flow.
     */
    public function rollback(int $flowId, int $versionId): FlowVersion
    {
        $flow    = $this->findFlowOrFail($flowId);
        $version = FlowVersion::find($versionId);

        if (!$version || $version->flow_id !== $flow->id) {
            throw new RuntimeException("Version #{$versionId} not found for flow #{$flowId}.");
        }

        return DB::transaction(function () use ($flow, $version): FlowVersion {
            // Delete live nodes & connections
            AutomationNode::where('flow_id', $flow->id)->delete();
            AutomationConnection::where('flow_id', $flow->id)->delete();

            // Re-create nodes from snapshot (omit id/timestamps so new rows are inserted)
            $nodeIdMap = [];
            foreach ($version->nodes_snapshot as $nodeData) {
                $oldId = $nodeData['id'];
                unset($nodeData['id'], $nodeData['created_at'], $nodeData['updated_at']);
                $nodeData['flow_id'] = $flow->id;
                $newNode = AutomationNode::create($nodeData);
                $nodeIdMap[$oldId] = $newNode->id;
            }

            // Re-create connections, remapping node IDs
            foreach ($version->connections_snapshot as $connData) {
                unset($connData['id'], $connData['created_at'], $connData['updated_at']);
                $connData['flow_id']        = $flow->id;
                $connData['source_node_id'] = $nodeIdMap[$connData['source_node_id']] ?? $connData['source_node_id'];
                $connData['target_node_id'] = $nodeIdMap[$connData['target_node_id']] ?? $connData['target_node_id'];
                AutomationConnection::create($connData);
            }

            // Restore flow meta
            if (!empty($version->flow_meta)) {
                $flow->fill($version->flow_meta)->save();
            }

            // Snapshot the restored state as a new version
            return $this->createVersion(
                $flow->id,
                "Rollback to v{$version->version_number}",
            );
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function findFlowOrFail(int $flowId): AutomationFlow
    {
        $flow = AutomationFlow::find($flowId);
        if (!$flow) {
            throw new RuntimeException("AutomationFlow #{$flowId} not found.");
        }
        return $flow;
    }
}
