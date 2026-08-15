<?php

declare(strict_types=1);

namespace Modules\Workflow\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Workflow\Services\Actions\AchatsInventoryActionHandler;
use Modules\Workflow\Services\Actions\AiActionHandler;
use Modules\Workflow\Services\Actions\CalendarActionHandler;
use Modules\Workflow\Services\Actions\CrmSalesActionHandler;
use Modules\Workflow\Services\Actions\DataTransformHandler;
use Modules\Workflow\Services\Actions\DelayActionHandler;
use Modules\Workflow\Services\Actions\DocumentsActionHandler;
use Modules\Workflow\Services\Actions\EcommerceActionHandler;
use Modules\Workflow\Services\Actions\HelpdeskActionHandler;
use Modules\Workflow\Services\Actions\HrPayrollActionHandler;
use Modules\Workflow\Services\Actions\HttpActionHandler;
use Modules\Workflow\Services\Actions\InventoryAccountingActionHandler;
use Modules\Workflow\Services\Actions\LogisticsActionHandler;
use Modules\Workflow\Services\Actions\NotificationActionHandler;
use Modules\Workflow\Services\Actions\ProjectsActionHandler;
use Modules\Workflow\Services\Actions\QualityActionHandler;
use Modules\Workflow\Services\Actions\SalesManufacturingActionHandler;
use Modules\Workflow\Services\Actions\StrategyActionHandler;
use Modules\Workflow\Services\ApprovalWorkflowService;
use Modules\Workflow\Services\Automation\FlowExecutionEngine;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;
use Modules\Workflow\Services\TaskManagementService;
use Modules\Workflow\Services\WorkflowActionRegistry;
use Modules\Workflow\Services\WorkflowBuilderService;
use Modules\Workflow\Services\WorkflowEngineService;

class WorkflowServiceProvider extends ServiceProvider
{
    protected string $name = 'Workflow';

    public function register(): void
    {
        // ── Legacy / existing services ─────────────────────────────────────────
        $this->app->singleton(WorkflowEngineService::class);
        $this->app->singleton(TaskManagementService::class);
        $this->app->singleton(ApprovalWorkflowService::class);
        $this->app->singleton(WorkflowBuilderService::class);

        // ── Phase-39 action handlers — original batch ──────────────────────────
        $this->app->singleton(AchatsInventoryActionHandler::class);
        $this->app->singleton(CrmSalesActionHandler::class);
        $this->app->singleton(HrPayrollActionHandler::class);
        $this->app->singleton(InventoryAccountingActionHandler::class);
        $this->app->singleton(NotificationActionHandler::class);
        $this->app->singleton(SalesManufacturingActionHandler::class);

        // ── Phase-39 action handlers — remaining modules (new batch) ──────────
        $this->app->singleton(AiActionHandler::class);
        $this->app->singleton(CalendarActionHandler::class);
        $this->app->singleton(DataTransformHandler::class);
        $this->app->singleton(DelayActionHandler::class);
        $this->app->singleton(DocumentsActionHandler::class);
        $this->app->singleton(EcommerceActionHandler::class);
        $this->app->singleton(HelpdeskActionHandler::class);
        $this->app->singleton(HttpActionHandler::class);
        $this->app->singleton(LogisticsActionHandler::class);
        $this->app->singleton(ProjectsActionHandler::class);
        $this->app->singleton(QualityActionHandler::class);
        $this->app->singleton(StrategyActionHandler::class);

        // ── Action registry (resolves action keys → handler callables) ─────────
        $this->app->singleton(WorkflowActionRegistry::class, function ($app) {
            return new WorkflowActionRegistry(
                // Original batch
                $app->make(AchatsInventoryActionHandler::class),
                $app->make(CrmSalesActionHandler::class),
                $app->make(HrPayrollActionHandler::class),
                $app->make(InventoryAccountingActionHandler::class),
                $app->make(NotificationActionHandler::class),
                $app->make(SalesManufacturingActionHandler::class),
                // New batch
                $app->make(AiActionHandler::class),
                $app->make(CalendarActionHandler::class),
                $app->make(DataTransformHandler::class),
                $app->make(DelayActionHandler::class),
                $app->make(DocumentsActionHandler::class),
                $app->make(EcommerceActionHandler::class),
                $app->make(HelpdeskActionHandler::class),
                $app->make(HttpActionHandler::class),
                $app->make(LogisticsActionHandler::class),
                $app->make(ProjectsActionHandler::class),
                $app->make(QualityActionHandler::class),
                $app->make(StrategyActionHandler::class),
            );
        });

        // ── Phase-39 n8n Node Engine (Phase 39 — universal registry + executor) ──
        $this->app->singleton(NodeTypeRegistry::class);
        $this->app->singleton(FlowExecutionEngine::class, function ($app) {
            return new FlowExecutionEngine($app->make(NodeTypeRegistry::class));
        });
        $this->app->alias(NodeTypeRegistry::class,   'automation_node_registry');
        $this->app->alias(FlowExecutionEngine::class, 'automation_flow_engine');

        // ── Service aliases ────────────────────────────────────────────────────
        $this->app->alias(WorkflowEngineService::class,    'workflow_engine');
        $this->app->alias(TaskManagementService::class,    'task_manager');
        $this->app->alias(ApprovalWorkflowService::class,  'approval_workflow');
        $this->app->alias(WorkflowBuilderService::class,   'workflow_builder');
        $this->app->alias(WorkflowActionRegistry::class,   'workflow_action_registry');
    }

    public function boot(): void
    {
        // apps/api doesn't exist in this repo (the real entry point is app/, per
        // CLAUDE.md) — this was a leftover comment from the WideHalo source
        // extraction. Without this call, Modules/Workflow/database/migrations/
        // (connector_definitions table, automation_flows versioning columns)
        // never ran; every other module's provider already does this.
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}
