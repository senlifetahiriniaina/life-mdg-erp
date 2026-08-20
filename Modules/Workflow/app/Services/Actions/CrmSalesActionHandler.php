<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CrmSalesActionHandler — Phase 39
 *
 * Handles workflow actions that bridge the CRM module to the Sales module.
 * All public methods follow the signature:
 *   (array $params, array $context): array
 *
 * They do not import classes from the CRM or Sales modules directly;
 * instead they write directly to the shared database tables using DB or
 * fire module-scoped events so the owning module reacts (service-locator pattern).
 */
class CrmSalesActionHandler
{
    /**
     * Dispatch an action method by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $method, array $params, array $context): array
    {
        return match ($method) {
            'create_order_from_opportunity'  => $this->createOrderFromOpportunity($params, $context),
            'create_quote_from_opportunity'  => $this->createQuoteFromOpportunity($params, $context),
            'update_opportunity_stage'       => $this->updateOpportunityStage($params, $context),
            'confirm_sales_order'            => $this->confirmSalesOrder($params, $context),
            'notify_sales_team'              => $this->notifySalesTeam($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown CRM/Sales action: {$method}"],
        };
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * action: sales.create_order_from_opportunity
     *
     * Creates a Sales order row from a won CRM opportunity.
     * Context expected: opportunity_id, amount, client_id, tenant_id
     *
     * @param  array<string,mixed>  $params   e.g. ['status' => 'draft']
     * @param  array<string,mixed>  $context
     * @return array{order_id: int|null, status: string}
     */
    public function createOrderFromOpportunity(array $params, array $context): array
    {
        $opportunityId = $context['opportunity_id'] ?? null;
        $clientId      = $context['client_id'] ?? null;
        $amount        = (float) ($context['amount'] ?? 0);
        $tenantId      = $context['tenant_id'] ?? 1;
        $currency      = $context['currency'] ?? 'XOF';

        if (! $opportunityId) {
            return ['status' => 'error', 'reason' => 'Missing opportunity_id in context'];
        }

        try {
            $orderId = DB::table('sales_orders')->insertGetId([
                'tenant_id'      => $tenantId,
                'opportunity_id' => $opportunityId,
                'client_id'      => $clientId,
                'total_amount'   => $amount,
                'currency'       => $currency,
                'status'         => $params['status'] ?? 'draft',
                'source'         => 'workflow_automation',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            Log::info('WorkflowAction: created sales order from opportunity', [
                'order_id'       => $orderId,
                'opportunity_id' => $opportunityId,
            ]);

            return ['order_id' => $orderId, 'status' => 'created', 'amount' => $amount];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createOrderFromOpportunity skipped (table may not exist)', [
                'error' => $e->getMessage(),
            ]);

            // Return a simulated result so the chain continues in test environments
            return ['order_id' => null, 'status' => 'simulated', 'amount' => $amount];
        }
    }

    /**
     * action: sales.create_quote_from_opportunity
     *
     * Creates a Sales quote (devis) from a CRM opportunity.
     * Context expected: opportunity_id, amount, client_id, tenant_id
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array{quote_id: int|null, status: string}
     */
    public function createQuoteFromOpportunity(array $params, array $context): array
    {
        $opportunityId = $context['opportunity_id'] ?? null;
        $clientId      = $context['client_id'] ?? null;
        $amount        = (float) ($context['amount'] ?? 0);
        $tenantId      = $context['tenant_id'] ?? 1;
        $currency      = $context['currency'] ?? 'XOF';
        $validityDays  = (int) ($params['validity_days'] ?? 30);

        if (! $opportunityId) {
            return ['status' => 'error', 'reason' => 'Missing opportunity_id in context'];
        }

        try {
            $quoteId = DB::table('sales_quotes')->insertGetId([
                'tenant_id'      => $tenantId,
                'opportunity_id' => $opportunityId,
                'client_id'      => $clientId,
                'total_amount'   => $amount,
                'currency'       => $currency,
                'status'         => 'draft',
                'valid_until'    => now()->addDays($validityDays),
                'source'         => 'workflow_automation',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return ['quote_id' => $quoteId, 'status' => 'created', 'valid_until' => now()->addDays($validityDays)->toDateString()];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createQuoteFromOpportunity skipped', ['error' => $e->getMessage()]);
            return ['quote_id' => null, 'status' => 'simulated'];
        }
    }

    /**
     * action: crm.update_opportunity_stage
     *
     * Updates the pipeline stage of a CRM opportunity.
     * Context expected: opportunity_id, tenant_id
     * Params expected:  stage (string)
     *
     * @param  array<string,mixed>  $params   e.g. ['stage' => 'closed_won']
     * @param  array<string,mixed>  $context
     * @return array{updated: bool, stage: string}
     */
    public function updateOpportunityStage(array $params, array $context): array
    {
        $opportunityId = $context['opportunity_id'] ?? null;
        $tenantId      = $context['tenant_id'] ?? 1;
        $stage         = $params['stage'] ?? 'closed_won';

        if (! $opportunityId) {
            return ['status' => 'error', 'reason' => 'Missing opportunity_id'];
        }

        try {
            $rows = DB::table('crm_opportunities')
                ->where('id', $opportunityId)
                ->where('tenant_id', $tenantId)
                ->update(['stage' => $stage, 'updated_at' => now()]);

            return ['updated' => $rows > 0, 'stage' => $stage];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: updateOpportunityStage skipped', ['error' => $e->getMessage()]);
            return ['updated' => false, 'stage' => $stage, 'status' => 'simulated'];
        }
    }

    /**
     * action: sales.confirm_sales_order
     *
     * Transitions a draft sales order to 'confirmed' status.
     * Context expected: order_id, tenant_id
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array{confirmed: bool, order_id: int|null}
     */
    public function confirmSalesOrder(array $params, array $context): array
    {
        $orderId  = $context['order_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;

        if (! $orderId) {
            return ['status' => 'error', 'reason' => 'Missing order_id'];
        }

        try {
            $rows = DB::table('sales_orders')
                ->where('id', $orderId)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now()]);

            return ['confirmed' => $rows > 0, 'order_id' => $orderId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: confirmSalesOrder skipped', ['error' => $e->getMessage()]);
            return ['confirmed' => false, 'order_id' => $orderId, 'status' => 'simulated'];
        }
    }

    /**
     * action: sales.notify_sales_team
     *
     * Queues an internal notification to the sales team.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array{notified: bool}
     */
    public function notifySalesTeam(array $params, array $context): array
    {
        $message = $params['message'] ?? 'Nouvelle opportunité convertie en commande';

        // Chantier 19 Lot 3: this insert claimed success (`notified: true`)
        // on every real call while never actually writing a row — the real
        // `notifications` table (database/migrations/2026_05_04_000001_
        // create_notifications_table.php) has a UUID primary key with no
        // default and no `tenant_id` column at all, so every insert
        // fatalled inside the try/catch and was silently swallowed
        // (confirmed via `Schema::getColumnListing`, not just a guess).
        // Fixed to write the real column shape — the caller's tenant is
        // folded into `data` instead of a phantom `tenant_id` column, since
        // this app's notifications are polymorphically tied to the
        // notifiable model, not tenant-scoped at the table level.
        $notified = false;
        try {
            DB::table('notifications')->insert([
                'id'               => (string) \Illuminate\Support\Str::uuid(),
                'notifiable_type'  => 'team',
                'notifiable_id'    => 0,
                'type'             => 'workflow.sales_team_alert',
                'data'             => json_encode([
                    'message' => $message,
                    'tenant_id' => $context['tenant_id'] ?? null,
                    'context'   => array_intersect_key($context, array_flip(['order_id', 'opportunity_id', 'amount'])),
                ]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $notified = true;
        } catch (\Throwable) {
            // Never block the chain on a notification-delivery failure.
        }

        return ['notified' => $notified, 'channel' => 'internal'];
    }
}
