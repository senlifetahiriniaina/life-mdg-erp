<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns contextual help content for in-app guides.
 * Content is keyed by `{module}.{page}` identifiers.
 *
 * @group Core - Help
 */
class HelpController extends Controller
{
    /** Static help registry — localisable via lang files in future iterations. */
    private const REGISTRY = [
        'crm.contacts' => [
            'title' => 'CRM — Contacts',
            'summary' => 'Manage your customers and prospects. Use filters to segment by type, owner, or lifecycle stage.',
            'tips' => [
                'Click "Import CSV" to bulk-import contacts from a spreadsheet.',
                'Assign an owner to route leads through the right sales rep.',
                'Tag contacts to group them for email campaigns.',
            ],
            'docs_url' => '/docs/crm/contacts',
        ],
        'crm.leads' => [
            'title' => 'CRM — Leads',
            'summary' => 'Capture and qualify inbound interest before promoting to Opportunities.',
            'tips' => [
                'AI scoring runs automatically every hour — check the score badge for quick prioritisation.',
                'Drag a lead card to a different column to update its status.',
                'Connect a web form to auto-create leads from your website.',
            ],
            'docs_url' => '/docs/crm/leads',
        ],
        'crm.opportunities' => [
            'title' => 'CRM — Pipeline',
            'summary' => 'Track deals through your sales pipeline. Move cards between stages by dragging.',
            'tips' => [
                'Set a close date and expected value on every opportunity for accurate forecasting.',
                'Log activities (calls, emails, meetings) to keep the timeline complete.',
                'Use the "Stuck deals" filter to surface opportunities with no recent activity.',
            ],
            'docs_url' => '/docs/crm/opportunities',
        ],
        'hr.employees' => [
            'title' => 'HR — Employee Directory',
            'summary' => 'Central record for every employee: personal info, role, department, and documents.',
            'tips' => [
                'Upload the signed contract in the "Documents" tab to keep everything in one place.',
                'Use the org chart view to visualise reporting lines.',
                'Grant portal access to let employees update their own profile and request leave.',
            ],
            'docs_url' => '/docs/hr/employees',
        ],
        'hr.leave' => [
            'title' => 'HR — Leave Management',
            'summary' => 'Approve or decline leave requests, view team calendars, and track balances.',
            'tips' => [
                'Configure accrual rules per leave type in Settings → Leave Types.',
                'Enable auto-approval for short leave types to reduce manager workload.',
                'The team calendar shows approved leave so you can spot coverage gaps.',
            ],
            'docs_url' => '/docs/hr/leave',
        ],
        'inventory.products' => [
            'title' => 'Inventory — Products',
            'summary' => 'Maintain your product catalogue, stock levels, and valuation across warehouses.',
            'tips' => [
                'Set a reorder point to receive automatic low-stock alerts.',
                'Use variants (size, colour) to manage product families with a single SKU root.',
                'Scan a barcode in the search bar to jump directly to a product.',
            ],
            'docs_url' => '/docs/inventory/products',
        ],
        'inventory.movements' => [
            'title' => 'Inventory — Stock Movements',
            'summary' => 'Full audit trail of every stock in/out/transfer across all warehouses.',
            'tips' => [
                'Filter by movement type to isolate sales, returns, or adjustments.',
                'Export to CSV for monthly stock reconciliation reports.',
                'Link a purchase order to automatically record the receipt movement.',
            ],
            'docs_url' => '/docs/inventory/movements',
        ],
        'accounting.invoices' => [
            'title' => 'Accounting — Invoices',
            'summary' => 'Create, send, and track customer invoices through their full lifecycle.',
            'tips' => [
                'Set up recurring invoices for subscription-based customers.',
                'Send a payment link directly from the invoice view.',
                'Use the aging report to chase overdue balances efficiently.',
            ],
            'docs_url' => '/docs/accounting/invoices',
        ],
        'helpdesk.tickets' => [
            'title' => 'Helpdesk — Tickets',
            'summary' => 'Track customer support requests from open to resolved with SLA enforcement.',
            'tips' => [
                'Configure SLA rules in Settings → Helpdesk → SLA Policies.',
                'Use macros to reply to common requests in one click.',
                'Internal notes are visible only to agents — use them for team coordination.',
            ],
            'docs_url' => '/docs/helpdesk/tickets',
        ],
        'projects.tasks' => [
            'title' => 'Projects — Tasks',
            'summary' => 'Organise work into projects, milestones, and tasks. Switch between Kanban, Gantt, and list views.',
            'tips' => [
                'Log time directly on a task for accurate billing and burndown tracking.',
                'Set dependencies to block a task until its predecessor is complete.',
                'Attach files and link documents from the Documents module.',
            ],
            'docs_url' => '/docs/projects/tasks',
        ],
        'pos.cashier' => [
            'title' => 'POS — Cashier',
            'summary' => 'Fast-entry point of sale with offline capability. Sync resumes automatically when back online.',
            'tips' => [
                'Press F2 to switch between cash and card payment quickly.',
                'Configure quick-add products on the numpad layout in POS Settings.',
                'Open the shift report at end of day to reconcile the cash drawer.',
            ],
            'docs_url' => '/docs/pos/cashier',
        ],
        'bi.dashboards' => [
            'title' => 'BI — Dashboards',
            'summary' => 'Build custom dashboards from 20+ chart types. Share with your team or schedule PDF reports.',
            'tips' => [
                'Drag and resize widgets to arrange your ideal layout.',
                'Use the AI Insights button to let Claude summarise trends automatically.',
                'Schedule a dashboard to be emailed as PDF every Monday morning.',
            ],
            'docs_url' => '/docs/bi/dashboards',
        ],
    ];

    /**
     * Return help content for a given context key.
     *
     * @queryParam key string required Context key in format `{module}.{page}`. Example: crm.contacts
     */
    public function show(Request $request): JsonResponse
    {
        $key = $request->query('key', '');

        if (! is_string($key) || $key === '') {
            return response()->json(['error' => 'Query parameter `key` is required.'], 422);
        }

        if (! isset(self::REGISTRY[$key])) {
            return response()->json([
                'title' => 'Help',
                'summary' => 'No help content available for this page yet.',
                'tips' => [],
                'docs_url' => '/docs',
            ]);
        }

        return response()->json(self::REGISTRY[$key]);
    }

    /**
     * Return all available help keys (for discovery/search).
     */
    public function index(): JsonResponse
    {
        $keys = array_map(
            fn ($key, $item) => ['key' => $key, 'title' => $item['title']],
            array_keys(self::REGISTRY),
            array_values(self::REGISTRY)
        );

        return response()->json(['data' => $keys]);
    }
}
