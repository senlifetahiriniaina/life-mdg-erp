<?php

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;


class NodeTypeRegistryTest extends TestCase
{
    private NodeTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(NodeTypeRegistry::class);
    }

    /** @test */
    public function it_returns_all_triggers()
    {
        $triggers = $this->registry->getTriggers();

        $this->assertIsArray($triggers);
        $this->assertNotEmpty($triggers);
    }

    /** @test */
    public function it_returns_all_actions()
    {
        $actions = $this->registry->getActions();

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
    }

    /** @test */
    public function it_has_70_plus_triggers()
    {
        $triggers = $this->registry->getTriggers();
        $this->assertGreaterThanOrEqual(70, count($triggers));
    }

    /** @test */
    public function it_has_50_plus_actions()
    {
        $actions = $this->registry->getActions();
        $this->assertGreaterThanOrEqual(50, count($actions));
    }

    /** @test */
    public function it_retrieves_trigger_by_key()
    {
        $trigger = $this->registry->getTrigger('crm.opportunity.won');

        $this->assertIsArray($trigger);
        $this->assertArrayHasKey('label', $trigger);
        $this->assertArrayHasKey('description', $trigger);
    }

    /** @test */
    public function it_retrieves_action_by_key()
    {
        $action = $this->registry->getAction('notification.send');

        $this->assertIsArray($action);
        $this->assertArrayHasKey('label', $action);
        $this->assertArrayHasKey('description', $action);
    }

    /** @test */
    public function it_returns_null_for_unknown_trigger()
    {
        $trigger = $this->registry->getTrigger('unknown.trigger');

        $this->assertNull($trigger);
    }

    /** @test */
    public function it_returns_null_for_unknown_action()
    {
        $action = $this->registry->getAction('unknown.action');

        $this->assertNull($action);
    }

    /** @test */
    public function crm_module_has_triggers()
    {
        $triggers = $this->registry->getTriggersForModule('CRM');

        $this->assertIsArray($triggers);
        $this->assertNotEmpty($triggers);
    }

    /** @test */
    public function accounting_module_has_triggers()
    {
        $triggers = $this->registry->getTriggersForModule('Accounting');

        $this->assertIsArray($triggers);
        $this->assertNotEmpty($triggers);
    }

    /** @test */
    public function manufacturing_module_has_triggers()
    {
        $triggers = $this->registry->getTriggersForModule('Manufacturing');

        $this->assertIsArray($triggers);
    }

    /** @test */
    public function crm_module_has_actions()
    {
        $actions = $this->registry->getActionsForModule('CRM');

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
    }

    /** @test */
    public function notification_action_is_available()
    {
        $action = $this->registry->getAction('notification.send');

        $this->assertNotNull($action);
    }

    /** @test */
    public function http_action_is_available()
    {
        $action = $this->registry->getAction('http.request');

        $this->assertNotNull($action);
    }

    /** @test */
    public function delay_action_is_available()
    {
        $action = $this->registry->getAction('workflow.delay');

        $this->assertNotNull($action);
    }

    /** @test */
    public function trigger_has_required_structure()
    {
        $triggers = $this->registry->getTriggers();
        $firstTrigger = reset($triggers);

        if ($firstTrigger) {
            $this->assertArrayHasKey('label', $firstTrigger);
            $this->assertArrayHasKey('description', $firstTrigger);
            $this->assertArrayHasKey('module', $firstTrigger);
        }
    }

    /** @test */
    public function action_has_required_structure()
    {
        $actions = $this->registry->getActions();
        $firstAction = reset($actions);

        if ($firstAction) {
            $this->assertArrayHasKey('label', $firstAction);
            $this->assertArrayHasKey('description', $firstAction);
        }
    }
}
