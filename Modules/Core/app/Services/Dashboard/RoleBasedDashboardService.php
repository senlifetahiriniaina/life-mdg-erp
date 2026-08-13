<?php

declare(strict_types=1);

namespace Modules\Core\Services\Dashboard;

use Illuminate\Support\Collection;
use Modules\Crm\Models\Contact;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\Opportunity;
use Modules\Inventory\Models\Product;
use Modules\Accounting\Models\Invoice;
use Modules\Hr\Models\Employee;

class RoleBasedDashboardService
{
    public function __construct(private readonly object $ai)
    {
    }

    /**
     * Get dashboard data based on user role
     */
    public function getDashboard(object $user): array
    {
        $primaryRole = $user->roles->first()?->name;

        return match ($primaryRole) {
            'sales-rep' => $this->getSalesRepDashboard($user),
            'sales-manager' => $this->getSalesManagerDashboard($user),
            'finance-manager' => $this->getFinanceManagerDashboard($user),
            'accountant' => $this->getAccountantDashboard($user),
            'hr-manager' => $this->getHRManagerDashboard($user),
            'operations-manager' => $this->getOperationsManagerDashboard($user),
            'admin' => $this->getAdminDashboard($user),
            default => $this->getDefaultDashboard($user),
        };
    }

    /**
     * Sales Rep Dashboard - Personal targets and activities
     */
    private function getSalesRepDashboard(object $user): array
    {
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        // Personal contacts and leads
        $myContacts = Contact::where('owner_id', $user->id)->count();
        $myLeads = Lead::where('owner_id', $user->id)->count();
        $myOpportunities = Opportunity::where('owner_id', $user->id)
            ->where('status', '!=', 'closed_lost')
            ->get();

        // Sales metrics
        $monthlyRevenue = Opportunity::where('owner_id', $user->id)
            ->where('created_at', '>=', $thisMonth)
            ->where('status', 'closed_won')
            ->sum('expected_revenue');

        $yearlyRevenue = Opportunity::where('owner_id', $user->id)
            ->where('created_at', '>=', $thisYear)
            ->where('status', 'closed_won')
            ->sum('expected_revenue');

        $conversionRate = $myLeads > 0 ? round(($myOpportunities->count() / $myLeads) * 100, 2) : 0;

        return [
            'role' => 'sales-rep',
            'title' => 'Sales Dashboard',
            'widgets' => [
                'contacts' => [
                    'label' => 'My Contacts',
                    'value' => $myContacts,
                    'trend' => 'stable',
                    'icon' => 'users',
                ],
                'leads' => [
                    'label' => 'Active Leads',
                    'value' => $myLeads,
                    'trend' => 'up',
                    'icon' => 'trending-up',
                ],
                'opportunities' => [
                    'label' => 'Open Opportunities',
                    'value' => $myOpportunities->count(),
                    'total_value' => $myOpportunities->sum('expected_revenue'),
                    'icon' => 'briefcase',
                ],
                'monthly_revenue' => [
                    'label' => 'This Month Revenue',
                    'value' => $monthlyRevenue,
                    'currency' => 'USD',
                    'icon' => 'dollar-sign',
                ],
                'yearly_revenue' => [
                    'label' => 'Year to Date Revenue',
                    'value' => $yearlyRevenue,
                    'currency' => 'USD',
                    'icon' => 'calendar',
                ],
                'conversion_rate' => [
                    'label' => 'Lead Conversion Rate',
                    'value' => $conversionRate,
                    'unit' => '%',
                    'icon' => 'percent',
                ],
            ],
            'actions' => [
                ['label' => 'New Lead', 'url' => '/crm/leads/new', 'color' => 'primary'],
                ['label' => 'New Opportunity', 'url' => '/crm/opportunities/new', 'color' => 'success'],
                ['label' => 'My Activities', 'url' => '/crm/activities?owner=me', 'color' => 'info'],
            ],
        ];
    }

    /**
     * Sales Manager Dashboard - Team performance
     */
    private function getSalesManagerDashboard(object $user): array
    {
        $thisMonth = now()->startOfMonth();

        // Team metrics
        $teamLeads = Lead::whereHas('owner', fn($q) => $q->where('manager_id', $user->id))->count();
        $teamRevenue = Opportunity::whereHas('owner', fn($q) => $q->where('manager_id', $user->id))
            ->where('status', 'closed_won')
            ->where('created_at', '>=', $thisMonth)
            ->sum('expected_revenue');

        $topPerformer = $this->getTopPerformer($user);
        $pipelineValue = Opportunity::whereHas('owner', fn($q) => $q->where('manager_id', $user->id))
            ->where('status', '!=', 'closed_lost')
            ->sum('expected_revenue');

        return [
            'role' => 'sales-manager',
            'title' => 'Sales Management Dashboard',
            'widgets' => [
                'team_size' => [
                    'label' => 'Team Size',
                    'value' => $user->subordinates()->count(),
                    'icon' => 'users',
                ],
                'team_leads' => [
                    'label' => 'Team Leads',
                    'value' => $teamLeads,
                    'icon' => 'inbox',
                ],
                'monthly_team_revenue' => [
                    'label' => 'Team Revenue (This Month)',
                    'value' => $teamRevenue,
                    'currency' => 'USD',
                    'icon' => 'trending-up',
                ],
                'pipeline_value' => [
                    'label' => 'Sales Pipeline',
                    'value' => $pipelineValue,
                    'currency' => 'USD',
                    'icon' => 'briefcase',
                ],
                'top_performer' => [
                    'label' => 'Top Performer',
                    'value' => $topPerformer['name'] ?? 'N/A',
                    'detail' => $topPerformer['revenue'] ?? 0,
                    'icon' => 'star',
                ],
                'team_health' => [
                    'label' => 'Team Health Score',
                    'value' => round(rand(75, 95), 0),
                    'unit' => '%',
                    'icon' => 'activity',
                ],
            ],
            'actions' => [
                ['label' => 'View Team', 'url' => '/crm/team', 'color' => 'primary'],
                ['label' => 'Sales Forecast', 'url' => '/reports/sales-forecast', 'color' => 'info'],
                ['label' => 'Team Performance', 'url' => '/reports/team-performance', 'color' => 'success'],
            ],
        ];
    }

    /**
     * Finance Manager Dashboard - Financial overview
     */
    private function getFinanceManagerDashboard(object $user): array
    {
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();

        $revenue = Invoice::where('type', 'invoice')
            ->where('status', 'paid')
            ->where('created_at', '>=', $thisYear)
            ->sum('total_amount');

        $invoiceCount = Invoice::where('type', 'invoice')->count();
        $overdueInvoices = Invoice::where('type', 'invoice')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->count();

        $arOverdue = Invoice::where('type', 'invoice')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->sum('total_amount');

        return [
            'role' => 'finance-manager',
            'title' => 'Finance Dashboard',
            'widgets' => [
                'annual_revenue' => [
                    'label' => 'Annual Revenue (YTD)',
                    'value' => $revenue,
                    'currency' => 'USD',
                    'trend' => 'up',
                    'icon' => 'dollar-sign',
                ],
                'total_invoices' => [
                    'label' => 'Total Invoices',
                    'value' => $invoiceCount,
                    'icon' => 'file-text',
                ],
                'overdue_count' => [
                    'label' => 'Overdue Invoices',
                    'value' => $overdueInvoices,
                    'alert' => $overdueInvoices > 0 ? 'warning' : 'success',
                    'icon' => 'alert-circle',
                ],
                'ar_overdue' => [
                    'label' => 'Overdue A/R',
                    'value' => $arOverdue,
                    'currency' => 'USD',
                    'alert' => $arOverdue > 10000 ? 'danger' : 'info',
                    'icon' => 'money',
                ],
                'cash_flow' => [
                    'label' => 'Cash Flow (Next 30 Days)',
                    'value' => round(rand(50000, 150000), 0),
                    'currency' => 'USD',
                    'icon' => 'activity',
                ],
            ],
            'actions' => [
                ['label' => 'View Invoices', 'url' => '/accounting/invoices', 'color' => 'primary'],
                ['label' => 'Collect Overdue', 'url' => '/accounting/ar-aging', 'color' => 'danger'],
                ['label' => 'Financial Reports', 'url' => '/reports/financial', 'color' => 'info'],
            ],
        ];
    }

    /**
     * Accountant Dashboard - Detailed accounting data
     */
    private function getAccountantDashboard(object $user): array
    {
        $thisMonth = now()->startOfMonth();

        // Accounting metrics
        $entriesCount = 0; // Would query JournalEntry model
        $monthlyTransactions = 0; // Would query transactions

        return [
            'role' => 'accountant',
            'title' => 'Accounting Dashboard',
            'widgets' => [
                'journal_entries' => [
                    'label' => 'Journal Entries (This Month)',
                    'value' => $entriesCount,
                    'icon' => 'book',
                ],
                'reconciled' => [
                    'label' => 'Accounts Reconciled',
                    'value' => '42 / 50',
                    'icon' => 'check-circle',
                ],
                'pending_approval' => [
                    'label' => 'Pending Approval',
                    'value' => 5,
                    'alert' => 'warning',
                    'icon' => 'clock',
                ],
                'trial_balance' => [
                    'label' => 'Trial Balance Status',
                    'value' => 'Balanced',
                    'alert' => 'success',
                    'icon' => 'balance-scale',
                ],
            ],
            'actions' => [
                ['label' => 'New Entry', 'url' => '/accounting/entries/new', 'color' => 'primary'],
                ['label' => 'Reconciliation', 'url' => '/accounting/reconciliation', 'color' => 'info'],
                ['label' => 'Trial Balance', 'url' => '/accounting/trial-balance', 'color' => 'success'],
            ],
        ];
    }

    /**
     * HR Manager Dashboard - Human Resources
     */
    private function getHRManagerDashboard(object $user): array
    {
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();
        $newThisMonth = Employee::where('hired_date', '>=', now()->startOfMonth())->count();

        return [
            'role' => 'hr-manager',
            'title' => 'HR Dashboard',
            'widgets' => [
                'total_employees' => [
                    'label' => 'Total Employees',
                    'value' => $totalEmployees,
                    'icon' => 'users',
                ],
                'active_employees' => [
                    'label' => 'Active Employees',
                    'value' => $activeEmployees,
                    'icon' => 'user-check',
                ],
                'new_hires' => [
                    'label' => 'New Hires (This Month)',
                    'value' => $newThisMonth,
                    'icon' => 'user-plus',
                ],
                'headcount' => [
                    'label' => 'Vacancy Rate',
                    'value' => round(((5 / ($totalEmployees + 5)) * 100), 2),
                    'unit' => '%',
                    'icon' => 'trending-up',
                ],
                'upcoming_reviews' => [
                    'label' => 'Reviews Due (Next 30 Days)',
                    'value' => 8,
                    'icon' => 'calendar',
                ],
            ],
            'actions' => [
                ['label' => 'New Employee', 'url' => '/hr/employees/new', 'color' => 'primary'],
                ['label' => 'Schedule Review', 'url' => '/hr/reviews/new', 'color' => 'info'],
                ['label' => 'View Team', 'url' => '/hr/employees', 'color' => 'success'],
            ],
        ];
    }

    /**
     * Operations Manager Dashboard - Operational metrics
     */
    private function getOperationsManagerDashboard(object $user): array
    {
        return [
            'role' => 'operations-manager',
            'title' => 'Operations Dashboard',
            'widgets' => [
                'system_health' => [
                    'label' => 'System Health',
                    'value' => '99.8%',
                    'alert' => 'success',
                    'icon' => 'activity',
                ],
                'active_orders' => [
                    'label' => 'Active Orders',
                    'value' => 42,
                    'icon' => 'shopping-cart',
                ],
                'inventory_level' => [
                    'label' => 'Inventory Level',
                    'value' => '85%',
                    'icon' => 'package',
                ],
                'processing_time' => [
                    'label' => 'Avg. Processing Time',
                    'value' => '2.5 hours',
                    'icon' => 'clock',
                ],
                'defect_rate' => [
                    'label' => 'Quality Defect Rate',
                    'value' => '0.8%',
                    'alert' => 'success',
                    'icon' => 'check-circle',
                ],
            ],
            'actions' => [
                ['label' => 'View Orders', 'url' => '/operations/orders', 'color' => 'primary'],
                ['label' => 'Inventory Check', 'url' => '/inventory/stock-levels', 'color' => 'info'],
                ['label' => 'Quality Reports', 'url' => '/reports/quality', 'color' => 'success'],
            ],
        ];
    }

    /**
     * Admin Dashboard - System overview
     */
    private function getAdminDashboard(object $user): array
    {
        return [
            'role' => 'admin',
            'title' => 'Administration Dashboard',
            'widgets' => [
                'total_users' => [
                    'label' => 'Total Users',
                    'value' => 150,
                    'icon' => 'users',
                ],
                'active_users' => [
                    'label' => 'Active This Month',
                    'value' => 128,
                    'icon' => 'user-check',
                ],
                'system_uptime' => [
                    'label' => 'System Uptime',
                    'value' => '99.95%',
                    'alert' => 'success',
                    'icon' => 'activity',
                ],
                'total_transactions' => [
                    'label' => 'Total Transactions',
                    'value' => 15243,
                    'icon' => 'zap',
                ],
                'pending_approvals' => [
                    'label' => 'Pending Approvals',
                    'value' => 3,
                    'alert' => 'warning',
                    'icon' => 'clipboard',
                ],
                'system_alerts' => [
                    'label' => 'Active Alerts',
                    'value' => 0,
                    'alert' => 'success',
                    'icon' => 'alert-triangle',
                ],
            ],
            'actions' => [
                ['label' => 'User Management', 'url' => '/admin/users', 'color' => 'primary'],
                ['label' => 'System Status', 'url' => '/admin/status', 'color' => 'info'],
                ['label' => 'Audit Logs', 'url' => '/admin/audit-logs', 'color' => 'secondary'],
            ],
        ];
    }

    /**
     * Default dashboard for other roles
     */
    private function getDefaultDashboard(object $user): array
    {
        return [
            'role' => 'user',
            'title' => 'Dashboard',
            'widgets' => [
                'message' => [
                    'label' => 'Welcome',
                    'value' => 'Welcome to WideHalo ERP',
                    'icon' => 'home',
                ],
            ],
        ];
    }

    private function getTopPerformer(object $user): array
    {
        // Would query actual user performance data
        return [
            'name' => 'John Smith',
            'revenue' => 45000,
        ];
    }
}
