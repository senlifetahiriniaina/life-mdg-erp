<?php

declare(strict_types=1);

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\Automation\AutomationNode;
use Modules\Workflow\Models\Automation\AutomationConnection;
use Modules\Workflow\Models\Automation\FlowVersion;
use Modules\Workflow\Services\FlowVersionService;
use Tests\TestCase;

/**
 * Tests for Item #25 — Flow versioning + rollback
 *
 * Covers:
 *  1. createVersion() creates a FlowVersion snapshot
 *  2. version_number increments on each publish
 *  3. is_published is set to true after first publish
 *  4. parent_version_id points to the latest version
 *  5. listVersions() returns all versions newest-first
 *  6. rollback() restores nodes from snapshot
 *  7. rollback() restores connections from snapshot
 *  8. rollback() creates a new version after restoration
 *  9. rollback() throws for unknown versionId
 * 10. rollback() throws for mismatched flow
 */
class FlowVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlowVersionService $service;
    private AutomationFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FlowVersionService();

        $this->flow = AutomationFlow::create([
            'tenant_id'      => 1,
            'key'            => 'test.versioning',
            'name'           => 'Versioning Test Flow',
            'trigger_type'   => 'manual',
            'is_active'      => true,
            'version_number' => 0,
            'is_published'   => false,
        ]);

        // Add a trigger node
        AutomationNode::create([
            'flow_id'     => $this->flow->id,
            'node_type'   => 'trigger',
            'node_key'    => 'crm.contact.created',
            'label'       => 'Contact Created',
            'position_x'  => 100,
            'position_y'  => 100,
        ]);
    }

    // ── Test 1 ──────────────────────────────────────────────────────────────────

    public function test_create_version_creates_a_snapshot(): void
    {
        $version = $this->service->createVersion($this->flow->id, 'Initial publish');

        $this->assertInstanceOf(FlowVersion::class, $version);
        $this->assertEquals($this->flow->id, $version->flow_id);
        $this->assertEquals('Initial publish', $version->label);
        $this->assertNotEmpty($version->nodes_snapshot);
        $this->assertDatabaseHas('flow_versions', ['flow_id' => $this->flow->id, 'version_number' => 1]);
    }

    // ── Test 2 ──────────────────────────────────────────────────────────────────

    public function test_version_number_increments_on_successive_publishes(): void
    {
        $v1 = $this->service->createVersion($this->flow->id);
        $v2 = $this->service->createVersion($this->flow->id);

        $this->assertEquals(1, $v1->version_number);
        $this->assertEquals(2, $v2->version_number);
    }

    // ── Test 3 ──────────────────────────────────────────────────────────────────

    public function test_is_published_set_to_true_after_publish(): void
    {
        $this->assertFalse((bool) $this->flow->is_published);

        $this->service->createVersion($this->flow->id);

        $this->flow->refresh();
        $this->assertTrue((bool) $this->flow->is_published);
    }

    // ── Test 4 ──────────────────────────────────────────────────────────────────

    public function test_parent_version_id_updated_on_publish(): void
    {
        $version = $this->service->createVersion($this->flow->id);
        $this->flow->refresh();

        $this->assertEquals($version->id, $this->flow->parent_version_id);
    }

    // ── Test 5 ──────────────────────────────────────────────────────────────────

    public function test_list_versions_returns_all_newest_first(): void
    {
        $this->service->createVersion($this->flow->id, 'v1');
        $this->service->createVersion($this->flow->id, 'v2');
        $this->service->createVersion($this->flow->id, 'v3');

        $versions = $this->service->listVersions($this->flow->id);

        $this->assertCount(3, $versions);
        $this->assertEquals(3, $versions->first()->version_number);
        $this->assertEquals(1, $versions->last()->version_number);
    }

    // ── Test 6 ──────────────────────────────────────────────────────────────────

    public function test_rollback_restores_nodes_from_snapshot(): void
    {
        // Publish with 1 node
        $v1 = $this->service->createVersion($this->flow->id, 'Before extra node');

        // Add a second node AFTER publishing
        AutomationNode::create([
            'flow_id'    => $this->flow->id,
            'node_type'  => 'action',
            'node_key'   => 'crm.create_contact',
            'label'      => 'New Action Node',
            'position_x' => 300,
            'position_y' => 100,
        ]);

        $this->assertCount(2, AutomationNode::where('flow_id', $this->flow->id)->get());

        // Rollback to v1 (which had only 1 node)
        $this->service->rollback($this->flow->id, $v1->id);

        // After rollback a new snapshot is created but live nodes should match v1
        $restoredNodes = AutomationNode::where('flow_id', $this->flow->id)->get();
        $this->assertCount(1, $restoredNodes);
        $this->assertEquals('trigger', $restoredNodes->first()->node_type);
    }

    // ── Test 7 ──────────────────────────────────────────────────────────────────

    public function test_rollback_restores_connections_from_snapshot(): void
    {
        // Add a second node and connection
        $actionNode = AutomationNode::create([
            'flow_id'    => $this->flow->id,
            'node_type'  => 'action',
            'node_key'   => 'crm.create_contact',
            'label'      => 'Action',
            'position_x' => 300,
            'position_y' => 100,
        ]);
        $triggerNode = AutomationNode::where('flow_id', $this->flow->id)->where('node_type', 'trigger')->first();

        AutomationConnection::create([
            'flow_id'        => $this->flow->id,
            'source_node_id' => $triggerNode->id,
            'target_node_id' => $actionNode->id,
            'condition_type' => 'always',
        ]);

        // Publish with 1 connection
        $v1 = $this->service->createVersion($this->flow->id, 'With connection');

        // Wipe connections manually
        AutomationConnection::where('flow_id', $this->flow->id)->delete();
        $this->assertCount(0, AutomationConnection::where('flow_id', $this->flow->id)->get());

        // Rollback
        $this->service->rollback($this->flow->id, $v1->id);

        $this->assertCount(1, AutomationConnection::where('flow_id', $this->flow->id)->get());
    }

    // ── Test 8 ──────────────────────────────────────────────────────────────────

    public function test_rollback_creates_a_new_version(): void
    {
        $v1 = $this->service->createVersion($this->flow->id, 'v1');
        $initialCount = FlowVersion::where('flow_id', $this->flow->id)->count();

        $this->service->rollback($this->flow->id, $v1->id);

        $afterCount = FlowVersion::where('flow_id', $this->flow->id)->count();
        $this->assertGreaterThan($initialCount, $afterCount);
    }

    // ── Test 9 ──────────────────────────────────────────────────────────────────

    public function test_rollback_throws_for_unknown_version(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->service->rollback($this->flow->id, 99999);
    }

    // ── Test 10 ─────────────────────────────────────────────────────────────────

    public function test_rollback_throws_for_version_belonging_to_different_flow(): void
    {
        $otherFlow = AutomationFlow::create([
            'tenant_id'      => 1,
            'key'            => 'other.flow',
            'name'           => 'Other Flow',
            'trigger_type'   => 'manual',
            'is_active'      => true,
            'version_number' => 0,
            'is_published'   => false,
        ]);

        $otherVersion = $this->service->createVersion($otherFlow->id);

        $this->expectException(\RuntimeException::class);

        $this->service->rollback($this->flow->id, $otherVersion->id);
    }
}
